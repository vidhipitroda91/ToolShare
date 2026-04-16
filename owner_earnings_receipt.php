<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/receipt_helpers.php';
require 'includes/site_chrome.php';

toolshare_require_signed_in();

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    die('Invalid booking.');
}

$viewerRole = toolshare_current_role();
$receipt = toolshare_owner_statement_receipt($pdo, $bookingId, (int)$_SESSION['user_id'], $viewerRole);
if (!$receipt) {
    die('Settlement statement not found or access denied.');
}

$isExecutiveViewer = in_array($viewerRole, ['owner_admin', 'admin'], true);
$pageTitle = $isExecutiveViewer ? 'Executive Booking Statement' : 'Lender Earnings Statement';
$focusTitle = $isExecutiveViewer ? 'Business Owner Statement' : 'Owner Statement';
$backHref = $isExecutiveViewer ? 'owner_revenue.php' : 'dashboard.php';
$backLabel = $isExecutiveViewer ? 'Back to Revenue' : 'Back to Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
</head>
<body>
<?php toolshare_render_focus_header([
    'kicker' => 'Receipt Center',
    'title' => $focusTitle,
    'back_href' => $backHref,
    'back_label' => $backLabel,
]); ?>
<?= toolshare_render_owner_receipt_html($receipt) ?>
<div style="max-width:900px; margin:0 auto 30px; display:flex; gap:12px; flex-wrap:wrap;">
    <a href="owner_earnings_receipt_pdf.php?booking_id=<?= (int)$bookingId ?>" style="display:inline-block; text-decoration:none; background:#15324a; color:#fff; padding:12px 18px; border-radius:999px; font-weight:700;">Download Statement PDF</a>
    <a href="<?= htmlspecialchars($backHref) ?>" style="display:inline-block; text-decoration:none; background:#fff; color:#15324a; padding:12px 18px; border-radius:999px; border:1px solid #cbd5e1; font-weight:700;"><?= htmlspecialchars($backLabel) ?></a>
</div>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
