<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/booking_extensions_bootstrap.php';
require 'includes/site_chrome.php';
require 'includes/marketplace_mail_helper.php';

toolshare_require_user();

$user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    die("Invalid booking.");
}

$stmt = $pdo->prepare("
    SELECT b.*, t.title
    FROM bookings b
    JOIN tools t ON b.tool_id = t.id
    WHERE b.id = ?
      AND b.renter_id = ?
      AND b.status = 'paid'
      AND b.returned_at IS NULL
      AND b.drop_off_datetime >= NOW()
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking not found or not eligible for extension.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dropoff_date = $_POST['dropoff_date'] ?? '';
    $dropoff_time = $_POST['dropoff_time'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($dropoff_date === '' || $dropoff_time === '') {
        $error = "Please choose new drop-off date and time.";
    } else {
        $new_dropoff = $dropoff_date . ' ' . $dropoff_time . ':00';
        if (strtotime($new_dropoff) <= strtotime($booking['drop_off_datetime'])) {
            $error = "New drop-off must be after your current drop-off time.";
        } else {
            $pending = $pdo->prepare("SELECT id FROM booking_extensions WHERE booking_id = ? AND status = 'pending' LIMIT 1");
            $pending->execute([$booking_id]);
            if ($pending->fetch()) {
                $error = "You already have a pending extension request for this booking.";
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO booking_extensions (booking_id, renter_id, owner_id, requested_dropoff_datetime, note, status)
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $insert->execute([$booking_id, $booking['renter_id'], $booking['owner_id'], $new_dropoff, $note !== '' ? $note : null]);
                toolshare_mail_send_extension_requested_notification($pdo, $booking_id, $new_dropoff);
                header("Location: dashboard.php?msg=extension_requested");
                exit;
            }
        }
    }
}

$current_dropoff = date('Y-m-d\TH:i', strtotime($booking['drop_off_datetime']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Extension | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; color: #1e293b; }
        .wrap { max-width: 720px; margin: 20px auto; padding: 0 18px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 22px; }
        .muted { color: #64748b; font-size: 14px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; }
        input, textarea { width: 100%; border: 1px solid #dbe3ee; border-radius: 10px; padding: 10px; font-size: 14px; }
        textarea { min-height: 90px; resize: vertical; }
        .btn { display: inline-block; margin-top: 14px; background: #1a3654; color: #fff; text-decoration: none; border: none; border-radius: 10px; padding: 10px 14px; font-weight: 700; cursor: pointer; }
        .btn-secondary { background: #fff; color: #1a3654; border: 1px solid #cbd5e1; margin-left: 8px; }
        .error { background:#fff1f2; border:1px solid #fecdd3; color:#9f1239; padding:10px; border-radius:8px; margin-top:12px; }
    </style>
</head>
<body>
    <?php toolshare_render_focus_header([
        'kicker' => 'Booking Management',
        'title' => 'Request Extension',
        'back_href' => 'dashboard.php',
        'back_label' => 'Back to Dashboard',
    ]); ?>
    <div class="wrap">
        <div class="card">
            <h2>Request Extension</h2>
            <p class="muted">Tool: <strong><?= htmlspecialchars($booking['title']) ?></strong></p>
            <p class="muted">Current drop-off: <strong><?= date('M d, Y h:i A', strtotime($booking['drop_off_datetime'])) ?></strong></p>

            <?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="booking_id" value="<?= (int)$booking_id ?>">
                <div class="row">
                    <div>
                        <label>New Drop-off Date</label>
                        <input type="date" name="dropoff_date" min="<?= date('Y-m-d', strtotime($booking['drop_off_datetime'])) ?>" required>
                    </div>
                    <div>
                        <label>New Drop-off Time</label>
                        <input type="time" name="dropoff_time" required>
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <label>Note to Owner (optional)</label>
                    <textarea name="note" placeholder="Can I keep this tool longer?"></textarea>
                </div>
                <button type="submit" class="btn">Send Extension Request</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    <?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
