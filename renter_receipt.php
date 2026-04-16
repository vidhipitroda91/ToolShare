<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/receipt_helpers.php';
require 'includes/site_chrome.php';

toolshare_require_user();

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    die('Invalid booking.');
}

$receipt = toolshare_renter_receipt($pdo, $bookingId, (int)$_SESSION['user_id']);
if (!$receipt) {
    die('Receipt not found or access denied.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renter Receipt | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
</head>
<body>
<?php toolshare_render_focus_header([
    'kicker' => 'Receipt Center',
    'title' => 'Renter Receipt',
    'back_href' => 'dashboard.php',
    'back_label' => 'Back to Dashboard',
]); ?>
<?= toolshare_render_renter_receipt_html($receipt) ?>
<div style="max-width:900px; margin:0 auto 30px; display:flex; gap:12px; flex-wrap:wrap;">
    <a href="renter_receipt_pdf.php?booking_id=<?= (int)$bookingId ?>" style="display:inline-block; text-decoration:none; background:#15324a; color:#fff; padding:12px 18px; border-radius:999px; font-weight:700;">Download Receipt PDF</a>
    <a href="dashboard.php" style="display:inline-block; text-decoration:none; background:#fff; color:#15324a; padding:12px 18px; border-radius:999px; border:1px solid #cbd5e1; font-weight:700;">Back to Dashboard</a>
</div>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
