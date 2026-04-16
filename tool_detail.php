<?php
session_start();
require 'config/db.php';
require 'includes/site_chrome.php';

if (!isset($_GET['id'])) {
    die("Tool ID missing.");
}

$tool_id = $_GET['id'];

// 1. Fetch tool details and owner info
$stmt = $pdo->prepare("SELECT tools.*, users.full_name, users.phone FROM tools JOIN users ON tools.owner_id = users.id WHERE tools.id = ?");
$stmt->execute([$tool_id]);
$tool = $stmt->fetch();

if (!$tool) {
    die("Tool not found.");
}

// 2. CHECK AVAILABILITY: any paid booking that has not ended yet
$stmt_check = $pdo->prepare("SELECT duration_count, duration_type, pick_up_datetime, drop_off_datetime
    FROM bookings 
    WHERE tool_id = ? 
    AND status = 'paid' 
    AND drop_off_datetime >= NOW()
    ORDER BY
        CASE
            WHEN NOW() BETWEEN pick_up_datetime AND drop_off_datetime THEN 0
            ELSE 1
        END,
        pick_up_datetime ASC
    LIMIT 1");
$stmt_check->execute([$tool_id]);
$active_rental = $stmt_check->fetch();

$availability_note = '';
$time_hint = '';
if ($active_rental) {
    $now = new DateTime();
    $pickup = new DateTime($active_rental['pick_up_datetime']);
    $dropoff = new DateTime($active_rental['drop_off_datetime']);

    if ($now >= $pickup && $now <= $dropoff) {
        $availability_note = 'Currently Not Available';
        $target = $dropoff;
        $time_hint = 'Available in ';
    } else {
        $availability_note = 'Booked (Upcoming)';
        $target = $pickup;
        $time_hint = 'Unavailable for ';
    }

    $seconds = max(0, $target->getTimestamp() - $now->getTimestamp());
    $hours = (int) ceil($seconds / 3600);
    if ($hours >= 24) {
        $days = (int) ceil($hours / 24);
        $time_hint .= $days . ' day(s)';
    } else {
        $time_hint .= $hours . ' hour(s)';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tool['title']); ?> | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root { --primary: #15324a; --accent: #1f6f78; --text: #2d3748; --light-bg: #f8fafc; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: linear-gradient(180deg, #f5f7fb 0%, #ffffff 100%); color: var(--text); line-height: 1.6; }
        .main-container { display: grid; grid-template-columns: 1.5fr 1fr; max-width: 1300px; margin: 0 auto; padding: 24px 8% 40px; gap: 60px; }
        
        .product-display { display: flex; flex-direction: column; gap: 30px; }
        .category-tag { background: #e2e8f0; color: #475569; padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; width: fit-content; }
        .tool-title { font-size: 2.5rem; font-weight: 800; color: var(--primary); margin: 10px 0; letter-spacing: -1px; }
        .image-gallery { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 500px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .tool-image { max-width: 100%; height: auto; }
        
        .sidebar { position: sticky; top: 120px; height: fit-content; }
        .rental-card { background: white; border: 1px solid #e2e8f0; border-radius: 24px; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .owner-info { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9; }
        .owner-avatar { width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
        .availability-status { font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; margin-bottom: 20px; }
        .btn-rent { display: block; width: 100%; padding: 20px; background: var(--accent); color: white; text-align: center; text-decoration: none; font-weight: 800; border-radius: 100px; font-size: 1.1rem; transition: 0.3s; border: none; cursor: pointer; margin-bottom: 25px; }
        .btn-disabled { display: block; width: 100%; padding: 20px; background: #e2e8f0; color: #94a3b8; text-align: center; font-weight: 800; border-radius: 100px; cursor: not-allowed; border: none; margin-bottom: 25px; }
        
        .pricing-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 25px; }
        .pricing-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; }
        .price-label { font-weight: 500; color: #64748b; }
        .price-amount { font-weight: 800; font-size: 1.2rem; color: var(--primary); }
        .deposit-notice { background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; padding: 12px; border-radius: 12px; font-size: 0.85rem; margin-bottom: 25px; }
        .location-section { margin-top: 25px; font-size: 0.9rem; color: #64748b; }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>

<main class="main-container">
    <div class="product-display">
        <div class="category-tag"><?php echo htmlspecialchars($tool['category']); ?></div>
        <h1 class="tool-title"><?php echo htmlspecialchars($tool['title']); ?></h1>
        <div class="image-gallery"><img src="<?php echo htmlspecialchars($tool['image_path']); ?>" class="tool-image"></div>
        <div><h3>Product Overview</h3><p><?php echo nl2br(htmlspecialchars($tool['description'])); ?></p></div>
    </div>

    <aside class="sidebar">
        <div class="rental-card">
            <div class="owner-info">
                <div class="owner-avatar"><?php echo substr($tool['full_name'], 0, 1); ?></div>
                <div><div style="font-size: 0.8rem; color: #64748b;">Listed by</div><div style="font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($tool['full_name']); ?></div></div>
            </div>

            <div class="availability-status">
                <?php if ($active_rental): ?>
                    <span style="color: #dc2626;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" style="vertical-align: middle; margin-right: 5px;"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12zm-1-7h2v4H9v-4zm0-2h2v2H9V7z"></path></svg>
                        <?= htmlspecialchars($availability_note) ?>
                    </span>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 6px; line-height: 1.5;">
                        <?= htmlspecialchars($time_hint) ?>
                    </div>
                <?php else: ?>
                    <span style="color: #059669;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" style="vertical-align: middle; margin-right: 5px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        Currently Available
                    </span>
                <?php endif; ?>
            </div>

            

            <div class="pricing-list">
                <div class="pricing-item"><span class="price-label">Hourly</span><span class="price-amount">$<?php echo number_format($tool['price_hourly'], 2); ?></span></div>
                <div class="pricing-item"><span class="price-label">Daily</span><span class="price-amount">$<?php echo number_format($tool['price_daily'], 2); ?></span></div>
                <div class="pricing-item"><span class="price-label">Weekly</span><span class="price-amount">$<?php echo number_format($tool['price_weekly'], 2); ?></span></div>
            </div>

            <div class="deposit-notice"><strong>Refundable Deposit:</strong> $<?php echo number_format($tool['security_deposit'], 2); ?> required.</div>
            <div class="location-section"><strong>Pickup Location</strong><?php echo htmlspecialchars($tool['address']); ?></div><br>

            <?php if ($active_rental): ?>
                <button class="btn-disabled" disabled>Currently Unavailable</button>
            <?php else: ?>
                <a href="reservation_request.php?id=<?= $tool['id'] ?>" class="btn-rent">Reserve This Tool</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== (int)$tool['owner_id']): ?>
                <a href="chat.php?tool_id=<?= (int)$tool['id'] ?>&receiver_id=<?= (int)$tool['owner_id'] ?>" class="btn-rent" style="background:#1a3654; color:#fff; margin-top: 10px;">
                    Message Owner
                </a>
            <?php endif; ?>
        </div>
    </aside>
</main>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
