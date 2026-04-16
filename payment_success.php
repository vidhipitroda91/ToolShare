<?php
session_start();
require 'config/db.php';
require 'vendor/autoload.php';
require 'config/stripe.php';
require 'includes/auth.php';
require 'includes/payment_helpers.php';
require 'includes/site_chrome.php';
require 'includes/marketplace_mail_helper.php';

\Stripe\Stripe::setApiKey($stripeSecretKey);

toolshare_require_user();

if (!isset($_GET['session_id']) || empty($_GET['session_id'])) {
    die("Missing Stripe session.");
}

$user_id = (int)$_SESSION['user_id'];
$session_id = $_GET['session_id'];

try {
    $checkoutSession = \Stripe\Checkout\Session::retrieve($session_id);
} catch (Exception $e) {
    die("Unable to verify payment: " . htmlspecialchars($e->getMessage()));
}

if (($checkoutSession->payment_status ?? '') !== 'paid') {
    die("Payment not completed.");
}

$booking_id = (int)($checkoutSession->metadata->booking_id ?? $checkoutSession->client_reference_id ?? 0);
$session_renter_id = (int)($checkoutSession->metadata->renter_id ?? 0);
if ($booking_id <= 0 || $session_renter_id !== $user_id) {
    die("Payment verification failed.");
}

try {
    $finalized = toolshare_finalize_stripe_checkout_session($pdo, $checkoutSession);
    $alreadyPaid = (bool)$finalized['already_paid'];
    if (!$alreadyPaid) {
        toolshare_mail_send_payment_receipt_notifications($pdo, $booking_id);
    }
} catch (Throwable $e) {
    die("Payment verification failed: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 40px 20px; color: #1e293b; }
        .card { max-width: 560px; margin: 0 auto; background: white; border-radius: 18px; padding: 30px; border: 1px solid #e2e8f0; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        h1 { margin: 0 0 14px; color: #166534; }
        p { margin: 0 0 14px; line-height: 1.5; }
        .meta { margin: 18px 0; padding: 12px; background: #f1f5f9; border-radius: 10px; font-size: 14px; }
        .btn { display: inline-block; margin-top: 8px; background: #1a3654; color: #fff; text-decoration: none; padding: 11px 18px; border-radius: 10px; font-weight: 700; }
    </style>
</head>
<body>
    <?php toolshare_render_focus_header([
        'kicker' => 'Checkout',
        'title' => 'Payment Successful',
        'back_href' => 'dashboard.php',
        'back_label' => 'Back to Dashboard',
    ]); ?>
    <div class="card">
        <h1>Payment Successful</h1>
        <p><?= $alreadyPaid ? "This payment was already confirmed earlier." : "Your rental is now active for the booked time window." ?></p>
        <div class="meta">
            Booking ID: <strong>#<?= htmlspecialchars((string)$booking_id) ?></strong><br>
            Payment Session: <strong><?= htmlspecialchars($session_id) ?></strong>
        </div>
        <p>You can now message the owner from your dashboard for pickup coordination.</p>
        <button type="button" data-download-url="renter_receipt_pdf.php?booking_id=<?= (int)$booking_id ?>" class="btn js-direct-download" style="background:#1f6f78; margin-right:10px; border:none; cursor:pointer;">Download Receipt</button><br>
        <a href="dashboard.php" class="btn">Go to Dashboard</a>
    </div>
    <script>
        document.addEventListener('click', async function (event) {
            const button = event.target.closest('.js-direct-download');
            if (!button) return;
            event.preventDefault();
            const downloadUrl = button.dataset.downloadUrl;
            if (!downloadUrl) return;
            const originalText = button.textContent;
            button.textContent = 'Downloading...';
            button.disabled = true;
            try {
                const response = await fetch(downloadUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('Download failed');
                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const tempLink = document.createElement('a');
                tempLink.href = objectUrl;
                tempLink.download = '';
                document.body.appendChild(tempLink);
                tempLink.click();
                tempLink.remove();
                URL.revokeObjectURL(objectUrl);
            } catch (error) {
                window.alert('Unable to download the receipt right now.');
            } finally {
                button.textContent = originalText;
                button.disabled = false;
            }
        });
    </script>
    <?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
