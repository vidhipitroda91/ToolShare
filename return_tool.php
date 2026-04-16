<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/returns_bootstrap.php';
require 'includes/marketplace_mail_helper.php';

toolshare_require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
if ($bookingId <= 0) {
    header("Location: dashboard.php?msg=return_action_denied");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, status, pick_up_datetime, returned_at
    FROM bookings
    WHERE id = ? AND renter_id = ?
    LIMIT 1
");
$stmt->execute([$bookingId, (int)$_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking || $booking['status'] !== 'paid' || $booking['returned_at'] !== null || strtotime($booking['pick_up_datetime']) > time()) {
    header("Location: dashboard.php?msg=return_action_denied");
    exit;
}

$update = $pdo->prepare("UPDATE bookings SET returned_at = NOW() WHERE id = ? AND returned_at IS NULL");
$update->execute([$bookingId]);
if ($update->rowCount() > 0) {
    toolshare_mail_send_return_requested_notification($pdo, $bookingId);
}

header("Location: dashboard.php?msg=return_requested");
exit;
?>
