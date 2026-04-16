<?php
session_start();

require 'config/db.php';
require 'includes/auth.php';
require 'includes/returns_bootstrap.php';

toolshare_require_user();

$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
if ($bookingId <= 0) {
    header('Location: dashboard.php?msg=pickup_confirm_denied');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("
    UPDATE bookings
    SET pickup_confirmed_at = COALESCE(pickup_confirmed_at, NOW())
    WHERE id = ?
      AND renter_id = ?
      AND status = 'paid'
      AND returned_at IS NULL
      AND pick_up_datetime <= NOW()
");
$stmt->execute([$bookingId, $userId]);

if ($stmt->rowCount() > 0) {
    header('Location: dashboard.php?msg=pickup_confirmed');
    exit;
}

header('Location: dashboard.php?msg=pickup_confirm_denied');
exit;
