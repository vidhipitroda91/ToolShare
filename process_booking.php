<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/receipt_helpers.php';
require 'includes/marketplace_mail_helper.php';

toolshare_require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$renter_id = (int)$_SESSION['user_id'];
$tool_id = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
$pickup_date = trim($_POST['pickup_date'] ?? '');
$pickup_time = trim($_POST['pickup_time'] ?? '');
$dropoff_date = trim($_POST['dropoff_date'] ?? '');
$dropoff_time = trim($_POST['dropoff_time'] ?? '');
$pricing_type = trim($_POST['pricing_type'] ?? '');
$policy_agreed = isset($_POST['policy_agreed']) ? 1 : 0;

if ($tool_id <= 0) {
    die("Invalid tool selected.");
}

if ($pickup_date === '' || $pickup_time === '' || $dropoff_date === '' || $dropoff_time === '') {
    die("Please select complete pick-up and drop-off dates and times.");
}

if (!$policy_agreed) {
    die("You must agree to the terms and policies to proceed.");
}

$allowed_pricing_types = ['hourly', 'daily', 'weekly'];
if (!in_array($pricing_type, $allowed_pricing_types, true)) {
    die("Invalid pricing type selected.");
}

$pickup_str = $pickup_date . ' ' . $pickup_time . ':00';
$dropoff_str = $dropoff_date . ' ' . $dropoff_time . ':00';

$start = DateTime::createFromFormat('Y-m-d H:i:s', $pickup_str);
$end = DateTime::createFromFormat('Y-m-d H:i:s', $dropoff_str);

if (!$start || !$end || $start->format('Y-m-d H:i:s') !== $pickup_str || $end->format('Y-m-d H:i:s') !== $dropoff_str) {
    die("Invalid pick-up or drop-off date/time.");
}

if ($end <= $start) {
    die("Drop-off time must be after pick-up time.");
}

$stmt = $pdo->prepare("
    SELECT owner_id, price_hourly, price_daily, price_weekly, security_deposit
    FROM tools
    WHERE id = ?
");
$stmt->execute([$tool_id]);
$tool = $stmt->fetch();

if (!$tool) {
    die("Tool not found.");
}

if ((int)$tool['owner_id'] === $renter_id) {
    die("You cannot rent your own tool.");
}

$pricing_config = [
    'hourly' => ['column' => 'price_hourly', 'seconds' => 3600, 'duration_type' => 'hour'],
    'daily' => ['column' => 'price_daily', 'seconds' => 86400, 'duration_type' => 'day'],
    'weekly' => ['column' => 'price_weekly', 'seconds' => 604800, 'duration_type' => 'week'],
];

$selected_config = $pricing_config[$pricing_type];
$selected_price = isset($tool[$selected_config['column']]) ? (float)$tool[$selected_config['column']] : 0.0;

if ($selected_price <= 0) {
    die("The selected pricing option is unavailable for this tool.");
}

$seconds = $end->getTimestamp() - $start->getTimestamp();
$duration_count = max(1, (int) ceil($seconds / $selected_config['seconds']));
$duration_type = $selected_config['duration_type'];

$total_price = round($duration_count * $selected_price, 2);
$deposit = round((float)$tool['security_deposit'], 2);
$owner_id = (int)$tool['owner_id'];

$overlap = $pdo->prepare("
    SELECT id
    FROM bookings
    WHERE tool_id = ?
      AND status IN ('confirmed', 'paid')
      AND NOT (? <= pick_up_datetime OR ? >= drop_off_datetime)
    LIMIT 1
");
$overlap->execute([$tool_id, $dropoff_str, $pickup_str]);
if ($overlap->fetch()) {
    die("This tool is already booked for the selected time range. Please choose different dates/times.");
}

$sql = "INSERT INTO bookings (
            tool_id, renter_id, owner_id,
            duration_count, duration_type,
            total_price, security_deposit,
            status, pick_up_datetime, drop_off_datetime, policy_agreed
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$params = [
    $tool_id,
    $renter_id,
    $owner_id,
    $duration_count,
    $duration_type,
    $total_price,
    $deposit,
    $pickup_str,
    $dropoff_str,
    $policy_agreed,
];

try {
    if ($stmt->execute($params)) {
        $bookingId = (int)$pdo->lastInsertId();
        toolshare_sync_booking_charges($pdo, $bookingId);
        toolshare_mail_send_booking_request_notifications($pdo, $bookingId);
        header("Location: booking_confirmation.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
