<?php
session_start();
require 'config/db.php';
require 'vendor/autoload.php';
require 'config/stripe.php';
require 'includes/auth.php';
require 'includes/payment_helpers.php';

\Stripe\Stripe::setApiKey($stripeSecretKey);

toolshare_require_user();

if (!isset($_GET['booking_id']) || !ctype_digit((string)$_GET['booking_id'])) {
    die("Booking ID missing. Please initiate payment from your dashboard.");
}

$booking_id = (int)$_GET['booking_id'];
$user_id = (int)$_SESSION['user_id'];

// 2. Fetch booking and tool details
// Only allow payment if the owner has 'confirmed' the request
// Only allow payment if booking is confirmed and not past drop-off date
$stmt = $pdo->prepare("
    SELECT b.*, t.title 
    FROM bookings b 
    JOIN tools t ON b.tool_id = t.id 
    WHERE b.id = ? 
      AND b.renter_id = ? 
      AND b.status = 'confirmed'
      AND b.drop_off_datetime > NOW()
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking not found, already paid, or waiting for owner approval.");
}

// 3. Calculate total in cents for Stripe (e.g., $10.00 = 1000)
$lineItems = toolshare_payment_line_items($pdo, $booking_id, $booking);
$expectedTotal = toolshare_payment_expected_total_cents($pdo, $booking_id);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$baseUrl = $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);

try {
    // Create Stripe Checkout Session
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'client_reference_id' => (string)$booking_id,
        'metadata' => [
            'booking_id' => (string)$booking_id,
            'renter_id' => (string)$user_id,
            'tool_id' => (string)$booking['tool_id'],
        ],
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => $baseUrl . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . '/dashboard.php?msg=payment_cancelled',
    ]);

    $saveSession = $pdo->prepare("
        UPDATE bookings
        SET stripe_checkout_session_id = ?
        WHERE id = ?
          AND renter_id = ?
          AND status = 'confirmed'
    ");
    $saveSession->execute([$checkout_session->id, $booking_id, $user_id]);

    header("HTTP/1.1 303 See Other");
    header("Location: " . $checkout_session->url);
    exit;

} catch (Exception $e) {
    echo "Stripe Error: " . $e->getMessage();
}
