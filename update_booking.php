<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/booking_extensions_bootstrap.php';
require 'includes/returns_bootstrap.php';
require 'includes/receipt_helpers.php';
require 'includes/marketplace_mail_helper.php';

toolshare_require_user();

if (!isset($_GET['action'])) {
    header("Location: dashboard.php");
    exit;
}

$action = $_GET['action'];
$user_id = (int)$_SESSION['user_id'];

if (in_array($action, ['approve_extension', 'decline_extension'], true)) {
    $ext_id = isset($_GET['ext_id']) ? (int)$_GET['ext_id'] : 0;
    if ($ext_id <= 0) {
        die("Invalid extension request.");
    }

    $stmt = $pdo->prepare("
        SELECT be.*, b.tool_id, b.pick_up_datetime, b.drop_off_datetime, b.duration_type, b.status AS booking_status
        FROM booking_extensions be
        JOIN bookings b ON be.booking_id = b.id
        WHERE be.id = ? AND be.owner_id = ? AND be.status = 'pending'
    ");
    $stmt->execute([$ext_id, $user_id]);
    $ext = $stmt->fetch();

    if (!$ext) {
        die("Extension request not found or already processed.");
    }

    if ($action === 'decline_extension') {
        $decline = $pdo->prepare("UPDATE booking_extensions SET status = 'declined', reviewed_at = NOW() WHERE id = ?");
        $decline->execute([$ext_id]);
        toolshare_mail_send_extension_status_notification($pdo, (int)$ext['booking_id'], 'declined', (string)$ext['requested_dropoff_datetime']);
        header("Location: dashboard.php?msg=extension_declined");
        exit;
    }

    if ($ext['booking_status'] !== 'paid' || strtotime($ext['requested_dropoff_datetime']) <= strtotime($ext['drop_off_datetime'])) {
        $decline = $pdo->prepare("UPDATE booking_extensions SET status = 'declined', reviewed_at = NOW() WHERE id = ?");
        $decline->execute([$ext_id]);
        toolshare_mail_send_extension_status_notification($pdo, (int)$ext['booking_id'], 'declined', (string)$ext['requested_dropoff_datetime']);
        header("Location: dashboard.php?msg=extension_declined");
        exit;
    }

    // Ensure extension does not overlap other approved/paid bookings for the same tool
    $overlap = $pdo->prepare("
        SELECT id
        FROM bookings
        WHERE tool_id = ?
          AND id <> ?
          AND status IN ('confirmed', 'paid')
          AND NOT (? <= pick_up_datetime OR ? >= drop_off_datetime)
        LIMIT 1
    ");
    $overlap->execute([
        $ext['tool_id'],
        $ext['booking_id'],
        $ext['requested_dropoff_datetime'],
        $ext['drop_off_datetime'],
    ]);
    if ($overlap->fetch()) {
        header("Location: dashboard.php?msg=extension_conflict");
        exit;
    }

    // Recalculate rental duration and total price after extension using the booking's pricing unit
    $priceStmt = $pdo->prepare("SELECT price_hourly, price_daily, price_weekly FROM tools WHERE id = ?");
    $priceStmt->execute([$ext['tool_id']]);
    $tool = $priceStmt->fetch();
    if (!$tool) {
        die("Tool not found.");
    }

    $duration_map = [
        'hour' => ['column' => 'price_hourly', 'seconds' => 3600, 'duration_type' => 'hour'],
        'hourly' => ['column' => 'price_hourly', 'seconds' => 3600, 'duration_type' => 'hour'],
        'day' => ['column' => 'price_daily', 'seconds' => 86400, 'duration_type' => 'day'],
        'daily' => ['column' => 'price_daily', 'seconds' => 86400, 'duration_type' => 'day'],
        'week' => ['column' => 'price_weekly', 'seconds' => 604800, 'duration_type' => 'week'],
        'weekly' => ['column' => 'price_weekly', 'seconds' => 604800, 'duration_type' => 'week'],
    ];

    $existing_duration_type = strtolower((string)($ext['duration_type'] ?? 'hour'));
    $selected_duration = $duration_map[$existing_duration_type] ?? $duration_map['hour'];
    $selected_price = isset($tool[$selected_duration['column']]) ? (float)$tool[$selected_duration['column']] : 0.0;

    if ($selected_price <= 0) {
        die("The pricing configuration for this booking is unavailable.");
    }

    $start = new DateTime($ext['pick_up_datetime']);
    $newEnd = new DateTime($ext['requested_dropoff_datetime']);
    $seconds = max(0, $newEnd->getTimestamp() - $start->getTimestamp());
    $duration_count = max(1, (int) ceil($seconds / $selected_duration['seconds']));
    $new_total_price = round($duration_count * $selected_price, 2);

    $pdo->beginTransaction();
    try {
        $updateBooking = $pdo->prepare("
            UPDATE bookings
            SET drop_off_datetime = ?,
                duration_count = ?,
                duration_type = ?,
                total_price = ?
            WHERE id = ?
        ");
        $updateBooking->execute([
            $ext['requested_dropoff_datetime'],
            $duration_count,
            $selected_duration['duration_type'],
            $new_total_price,
            $ext['booking_id']
        ]);

        $approve = $pdo->prepare("UPDATE booking_extensions SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
        $approve->execute([$ext_id]);

        $pdo->commit();
        toolshare_sync_booking_charges($pdo, (int)$ext['booking_id']);
        toolshare_mail_send_extension_status_notification($pdo, (int)$ext['booking_id'], 'approved', (string)$ext['requested_dropoff_datetime']);
        header("Location: dashboard.php?msg=extension_approved");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: Unable to approve extension.");
    }
}

// Default booking status actions
if (!isset($_GET['id']) || (int)$_GET['id'] <= 0) {
    die("Invalid booking.");
}
$booking_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND owner_id = ?");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();
if (!$booking) {
    die("Unauthorized action or booking not found.");
}

$new_status = '';
switch ($action) {
    case 'confirm':
        $new_status = 'confirmed';
        break;
    case 'cancel':
        $new_status = 'cancelled';
        break;
    case 'complete':
    case 'approve_return':
        $new_status = 'completed';
        break;
    default:
        die("Invalid action requested.");
}

if ($action === 'approve_return') {
    $openDispute = $pdo->prepare("
        SELECT id
        FROM disputes
        WHERE booking_id = ?
          AND status IN ('pending', 'reviewing')
        LIMIT 1
    ");
    $openDispute->execute([$booking_id]);
    if ($openDispute->fetch()) {
        header("Location: dashboard.php?msg=return_action_denied");
        exit;
    }

    $update = $pdo->prepare("
        UPDATE bookings
        SET status = 'completed',
            return_reviewed_at = NOW(),
            deposit_status = 'full_refund',
            deposit_refund_amount = security_deposit
        WHERE id = ?
          AND owner_id = ?
          AND status = 'paid'
          AND returned_at IS NOT NULL
    ");
    if ($update->execute([$booking_id, $user_id]) && $update->rowCount() > 0) {
        toolshare_sync_booking_charges($pdo, $booking_id);
        toolshare_mail_send_return_approved_notification($pdo, $booking_id);
        header("Location: dashboard.php?msg=return_approved");
        exit;
    }
    header("Location: dashboard.php?msg=return_action_denied");
    exit;
}

$allowedCurrentStatuses = $action === 'confirm'
    ? ['pending']
    : ($action === 'cancel' ? ['pending', 'confirmed'] : []);

if (!empty($allowedCurrentStatuses)) {
    $placeholders = implode(',', array_fill(0, count($allowedCurrentStatuses), '?'));
    $update = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ? AND status IN ($placeholders)");
    $params = array_merge([$new_status, $booking_id], $allowedCurrentStatuses);
} else {
    $update = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $params = [$new_status, $booking_id];
}

if ($update->execute($params) && $update->rowCount() > 0) {
    toolshare_mail_send_booking_status_update($pdo, $booking_id, $new_status);
    header("Location: dashboard.php?msg=status_updated");
    exit;
}
echo "Error: Could not update the booking status. Please try again.";
?>
