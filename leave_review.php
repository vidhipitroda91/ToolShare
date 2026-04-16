<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/site_chrome.php';

toolshare_require_user();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if ($booking_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Verify the booking belongs to this user and is completed
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND renter_id = ? AND status = 'completed'");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) { die("Invalid request."); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = trim((string)($_POST['comment'] ?? ''));
    $tool_id = $booking['tool_id'];

    if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating between 1 and 5.';
    } else {
        $existing = $pdo->prepare("SELECT id FROM reviews WHERE booking_id = ? AND reviewer_id = ? LIMIT 1");
        $existing->execute([$booking_id, $user_id]);
        $existingReview = $existing->fetchColumn();

        if ($existingReview) {
            $update = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ?");
            $update->execute([$rating, $comment, $existingReview]);
        } else {
            $ins = $pdo->prepare("INSERT INTO reviews (tool_id, booking_id, reviewer_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$tool_id, $booking_id, $user_id, $rating, $comment]);
        }

        header("Location: dashboard.php?msg=review_posted");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Leave Review</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f4f4f4; }
        .card { background: white; padding: 20px; max-width: 400px; margin: auto; border-radius: 8px; }
        select, textarea { width: 100%; padding: 10px; margin: 10px 0; }
        .btn { background: #f39c12; color: white; border: none; padding: 10px; width: 100%; cursor: pointer; }
    </style>
</head>
<body>
    <?php toolshare_render_focus_header([
        'kicker' => 'Completed Booking',
        'title' => 'Leave a Review',
        'back_href' => 'dashboard.php',
        'back_label' => 'Back to Dashboard',
    ]); ?>
    <div class="card">
        <h2>Rate your Rental</h2>
        <?php if (isset($error)): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:10px; border-radius:8px; margin-bottom:12px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Rating:</label>
            <select name="rating">
                <option value="5">⭐⭐⭐⭐⭐ (Excellent)</option>
                <option value="4">⭐⭐⭐⭐ (Good)</option>
                <option value="3">⭐⭐⭐ (Average)</option>
                <option value="2">⭐⭐ (Poor)</option>
                <option value="1">⭐ (Terrible)</option>
            </select>
            <label>Comment:</label>
            <textarea name="comment" rows="4" placeholder="How was the tool?"></textarea>
            <button type="submit" class="btn">Submit Review</button>
        </form>
    </div>
    <?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
