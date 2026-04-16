<?php
session_start();
require 'config/db.php';
require 'includes/booking_extensions_bootstrap.php';
require 'includes/returns_bootstrap.php';
require 'includes/booking_charges_bootstrap.php';
require 'includes/support_helpers.php';
require 'includes/site_chrome.php';
require 'includes/auth.php';

toolshare_require_user();
toolshare_bootstrap_booking_charges($pdo);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// --- DATABASE QUERIES ---
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        b.duration_count AS active_duration_count,
        b.duration_type AS active_duration_type,
        b.drop_off_datetime AS active_dropoff
    FROM tools t
    LEFT JOIN bookings b ON b.id = (
        SELECT b2.id
        FROM bookings b2
        WHERE b2.tool_id = t.id
          AND b2.status = 'paid'
          AND NOW() BETWEEN b2.pick_up_datetime AND b2.drop_off_datetime
        ORDER BY b2.drop_off_datetime DESC
        LIMIT 1
    )
    WHERE t.owner_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$user_id]);
$my_tools = $stmt->fetchAll();

// Incoming Requests (You are the owner)
$stmt_requests = $pdo->prepare("SELECT b.*, t.title, u.full_name as renter_name FROM bookings b JOIN tools t ON b.tool_id = t.id JOIN users u ON b.renter_id = u.id WHERE b.owner_id = ? AND b.status = 'pending' ORDER BY b.created_at DESC");
$stmt_requests->execute([$user_id]);
$incoming_requests = $stmt_requests->fetchAll();

// My Requests Sent (You are the renter)
$stmt_my_requests = $pdo->prepare("
    SELECT
        b.*,
        t.title,
        u.full_name AS owner_name,
        COALESCE((
            SELECT d.status
            FROM disputes d
            WHERE d.booking_id = b.id
            ORDER BY d.created_at DESC
            LIMIT 1
        ), 'none') AS dispute_status,
        COALESCE((
            SELECT d.admin_decision
            FROM disputes d
            WHERE d.booking_id = b.id
            ORDER BY d.created_at DESC
            LIMIT 1
        ), 'pending') AS dispute_admin_decision,
        COALESCE((
            SELECT d.resolution_summary
            FROM disputes d
            WHERE d.booking_id = b.id
            ORDER BY d.created_at DESC
            LIMIT 1
        ), '') AS dispute_resolution_summary,
        COALESCE((
            SELECT d.updated_at
            FROM disputes d
            WHERE d.booking_id = b.id
            ORDER BY d.created_at DESC
            LIMIT 1
        ), NULL) AS dispute_updated_at,
        COALESCE((
            SELECT d.initiated_by
            FROM disputes d
            WHERE d.booking_id = b.id
            ORDER BY d.created_at DESC
            LIMIT 1
        ), 'owner') AS dispute_initiated_by
    FROM bookings b
    JOIN tools t ON b.tool_id = t.id
    JOIN users u ON t.owner_id = u.id
    WHERE b.renter_id = ?
    ORDER BY b.created_at DESC
");
$stmt_my_requests->execute([$user_id]);
$my_outgoing_requests = $stmt_my_requests->fetchAll();

$renter_active_count = 0;
$renter_pending_count = 0;
$renter_completed_count = 0;
$active_rented_tools = [];
foreach ($my_outgoing_requests as $request) {
    $isCurrentlyRented = $request['status'] === 'paid'
        && $request['returned_at'] === null;

    if ($isCurrentlyRented) {
        $renter_active_count++;
        $active_rented_tools[] = $request;
    } elseif (in_array($request['status'], ['pending', 'confirmed'], true)) {
        $renter_pending_count++;
    } elseif ($request['status'] === 'completed') {
        $renter_completed_count++;
    }
}

$stmt_owner_active_rentals = $pdo->prepare("
    SELECT
        b.*,
        COALESCE(t.title, 'Tool unavailable') AS title,
        COALESCE(u.full_name, 'Renter unavailable') AS renter_name
    FROM bookings b
    LEFT JOIN tools t ON b.tool_id = t.id
    LEFT JOIN users u ON b.renter_id = u.id
    WHERE b.owner_id = ?
      AND b.status = 'paid'
      AND b.returned_at IS NULL
    ORDER BY b.drop_off_datetime ASC, b.id DESC
");
$stmt_owner_active_rentals->execute([$user_id]);
$owner_active_rentals = $stmt_owner_active_rentals->fetchAll();
$current_rentals = $owner_active_rentals;
$active_owner_bookings_count = count($owner_active_rentals);
$has_live_rentals = !empty($active_rented_tools) || !empty($owner_active_rentals);

$stmt_returns = $pdo->prepare("
    SELECT
        b.*,
        t.title,
        u.full_name AS renter_name,
        COALESCE((
            SELECT d.status
            FROM disputes d
            WHERE d.booking_id = b.id
            ORDER BY d.created_at DESC
            LIMIT 1
        ), 'none') AS dispute_status
    FROM bookings b
    JOIN tools t ON b.tool_id = t.id
    JOIN users u ON b.renter_id = u.id
    WHERE b.owner_id = ?
      AND b.status = 'paid'
      AND b.returned_at IS NOT NULL
    ORDER BY b.returned_at DESC
");
$stmt_returns->execute([$user_id]);
$return_requests = $stmt_returns->fetchAll();

$stmt_msg = $pdo->prepare("SELECT m.*, t.title as tool_title, u_sender.full_name as sender_name, u_receiver.full_name as receiver_name FROM messages m JOIN tools t ON m.tool_id = t.id JOIN users u_sender ON m.sender_id = u_sender.id JOIN users u_receiver ON m.receiver_id = u_receiver.id WHERE m.id IN (SELECT MAX(id) FROM messages WHERE sender_id = ? OR receiver_id = ? GROUP BY tool_id, LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)) ORDER BY m.created_at DESC");
$stmt_msg->execute([$user_id, $user_id]);
$recent_chats = $stmt_msg->fetchAll();
$support_threads = toolshare_support_fetch_recent_threads($pdo, (int)$user_id, 6);

$recent_messages = [];
foreach ($recent_chats as $chat) {
    $other_id = ($chat['sender_id'] == $user_id) ? $chat['receiver_id'] : $chat['sender_id'];
    $other_name = ($chat['sender_id'] == $user_id) ? $chat['receiver_name'] : $chat['sender_name'];
    $recent_messages[] = [
        'thread_type' => 'chat',
        'contact_name' => $other_name,
        'context' => (string)$chat['tool_title'],
        'preview' => (string)$chat['message_text'],
        'created_at' => (string)$chat['created_at'],
        'href' => 'chat.php?tool_id=' . (int)$chat['tool_id'] . '&receiver_id=' . (int)$other_id,
    ];
}
foreach ($support_threads as $thread) {
    $recent_messages[] = [
        'thread_type' => 'support',
        'contact_name' => (string)$thread['name'],
        'context' => (string)$thread['subtitle'],
        'preview' => (string)$thread['preview'],
        'created_at' => (string)$thread['created_at'],
        'href' => (string)$thread['href'],
    ];
}
usort($recent_messages, static function (array $a, array $b): int {
    return strcmp((string)$b['created_at'], (string)$a['created_at']);
});
$recent_messages = array_slice($recent_messages, 0, 10);

$stmt_ext = $pdo->prepare("
    SELECT
        be.*,
        b.tool_id,
        b.drop_off_datetime AS current_dropoff,
        t.title,
        u.full_name AS renter_name
    FROM booking_extensions be
    JOIN bookings b ON be.booking_id = b.id
    JOIN tools t ON b.tool_id = t.id
    JOIN users u ON be.renter_id = u.id
    WHERE be.owner_id = ?
      AND be.status = 'pending'
    ORDER BY be.created_at DESC
");
$stmt_ext->execute([$user_id]);
$extension_requests = $stmt_ext->fetchAll();

$stmt_earnings = $pdo->prepare("
    SELECT
        b.id,
        t.title,
        u.full_name AS renter_name,
        b.status,
        b.total_price,
        b.pick_up_datetime,
        b.drop_off_datetime,
        COALESCE(bc.owner_platform_fee_amount, ROUND(b.total_price * 0.03, 2)) AS owner_fee,
        COALESCE(bc.deposit_deduction_amount, 0) AS deposit_kept,
        COALESCE(bc.owner_net_payout, ROUND(b.total_price * 0.97, 2)) AS owner_payout,
        COALESCE(bc.owner_net_payout, ROUND(b.total_price * 0.97, 2)) + COALESCE(bc.deposit_deduction_amount, 0) AS owner_total_received
    FROM bookings b
    JOIN tools t ON b.tool_id = t.id
    JOIN users u ON b.renter_id = u.id
    LEFT JOIN booking_charges bc ON bc.booking_id = b.id
    WHERE b.owner_id = ?
      AND b.status = 'completed'
    ORDER BY b.id DESC
    LIMIT 12
");
$stmt_earnings->execute([$user_id]);
$earnings_rows = $stmt_earnings->fetchAll();

$stmt_earnings_summary = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_price ELSE 0 END), 0) AS settled_revenue,
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN COALESCE(bc.owner_net_payout, b.total_price * 0.97) + COALESCE(bc.deposit_deduction_amount, 0) ELSE 0 END), 0) AS released_earnings,
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_bookings,
        COALESCE(SUM(CASE WHEN b.status = 'paid' AND b.returned_at IS NULL THEN 1 ELSE 0 END), 0) AS active_owner_bookings
    FROM bookings b
    LEFT JOIN booking_charges bc ON bc.booking_id = b.id
    WHERE b.owner_id = ?
");
$stmt_earnings_summary->execute([$user_id]);
$earnings_summary = $stmt_earnings_summary->fetch();
$earnings_summary['active_owner_bookings'] = $active_owner_bookings_count;

$flashMessage = '';
$flashTone = 'success';
if (!empty($_GET['msg'])) {
    switch ((string)$_GET['msg']) {
        case 'extension_requested':
            $flashMessage = 'Extension request submitted to owner.';
            break;
        case 'extension_approved':
            $flashMessage = 'Extension request approved.';
            break;
        case 'extension_declined':
            $flashMessage = 'Extension request declined.';
            break;
        case 'extension_conflict':
            $flashMessage = 'Extension could not be approved because another booking overlaps that time.';
            $flashTone = 'error';
            break;
        case 'return_requested':
            $flashMessage = 'Return request sent to the owner for review.';
            break;
        case 'return_approved':
            $flashMessage = 'Return approved and the deposit was marked for full refund.';
            break;
        case 'dispute_submitted':
            $flashMessage = 'Dispute submitted successfully. The case has been escalated for operations review, and the customer may be contacted by email or phone if more details are needed.';
            break;
        case 'dispute_exists':
            $flashMessage = 'An open dispute already exists for this booking.';
            $flashTone = 'error';
            break;
        case 'renter_dispute_submitted':
            $flashMessage = 'Problem report submitted successfully. Operations will review the renter-side claim and may contact you if more evidence is needed.';
            break;
        case 'pickup_confirmed':
            $flashMessage = 'Pickup confirmed. The rental is now marked as in your possession.';
            break;
        case 'pickup_confirm_denied':
            $flashMessage = 'Pickup confirmation is not available for that booking right now.';
            $flashTone = 'error';
            break;
        case 'return_action_denied':
            $flashMessage = 'That return action is not available for this booking.';
            $flashTone = 'error';
            break;
        case 'status_updated':
            $flashMessage = 'Booking status updated successfully.';
            break;
        case 'payment_cancelled':
            $flashMessage = 'Payment was cancelled. The booking is still waiting for payment in your dashboard.';
            $flashTone = 'error';
            break;
        case 'updated':
            $flashMessage = 'Tool listing updated successfully.';
            break;
        case 'deleted':
            $flashMessage = 'Tool listing deleted successfully.';
            break;
        case 'review_posted':
            $flashMessage = 'Your review was saved successfully.';
            break;
    }
}

function toolshare_active_rental_status(array $rental): array
{
    $pickupTs = strtotime((string)($rental['pick_up_datetime'] ?? ''));
    $dropoffTs = strtotime((string)($rental['drop_off_datetime'] ?? ''));
    $now = time();

    if ($pickupTs !== false && $pickupTs > $now) {
        return ['label' => 'Upcoming', 'class' => 'badge-upcoming'];
    }

    if ($dropoffTs !== false && $dropoffTs < $now) {
        return ['label' => 'Overdue', 'class' => 'badge-overdue'];
    }

    return ['label' => 'Active Now', 'class' => 'badge-live'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root { --primary: #15324a; --accent: #1f6f78; --bg: #f8fafc; --text: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 100%); color: var(--text); }
        .main-content { width: min(1320px, 94%); margin: 0 auto; padding: 20px 0 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: rgba(255,255,255,0.84); padding: 20px; border-radius: 22px; box-shadow: 0 18px 35px rgba(0,0,0,0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.8); }
        .stat-link { text-decoration: none; color: inherit; display: block; }
        .stat-link .stat-card { transition: 0.2s; }
        .stat-link:hover .stat-card { transform: translateY(-2px); box-shadow: 0 10px 18px rgba(0,0,0,0.08); }
        .stat-card h4 { font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        .stat-card p { font-size: 24px; font-weight: 800; color: var(--primary); }
        .card { background: rgba(255,255,255,0.9); border-radius: 24px; padding: 25px; margin-bottom: 30px; box-shadow: 0 18px 35px rgba(0,0,0,0.05); border: 1px solid rgba(255,255,255,0.8); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-header h3 { font-size: 18px; color: var(--primary); }
        .section-label { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; background: #e0f2fe; color: #0f4c81; font-size: 12px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; margin-bottom: 14px; }
        .section-label-renting { background: #ecfdf5; color: #166534; }
        .section-label-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .section-heading { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin: 34px 0 18px; }
        .section-heading h2 { font-size: 24px; color: var(--primary); margin-bottom: 6px; }
        .section-heading p { color: #64748b; font-size: 14px; max-width: 760px; line-height: 1.5; }
        .role-stats { margin-bottom: 24px; }
        .dual-active-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px; margin-bottom: 30px; align-items: start; }
        .sub-card { background: rgba(255,255,255,0.92); border-radius: 24px; padding: 22px; box-shadow: 0 14px 28px rgba(0,0,0,0.05); border: 1px solid rgba(255,255,255,0.85); }
        .sub-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
        .sub-card-count { min-width: 38px; height: 38px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #eff6ff; color: #1d4ed8; font-size: 14px; font-weight: 800; }
        .sub-card h3 { font-size: 18px; color: var(--primary); margin-bottom: 6px; }
        .sub-card p { color: #64748b; font-size: 14px; margin-bottom: 0; }
        .sub-list { display: flex; flex-direction: column; gap: 10px; max-height: 360px; overflow-y: auto; padding-right: 4px; }
        .sub-item { border: 1px solid #e2e8f0; border-radius: 18px; padding: 14px 16px; background: #fff; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 14px; align-items: center; }
        .sub-item-main { min-width: 0; }
        .sub-item-top { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
        .sub-item-title { font-size: 16px; color: var(--primary); font-weight: 800; margin: 0; }
        .sub-item-booking { font-size: 12px; color: #64748b; font-weight: 700; }
        .sub-item-meta-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .sub-item-meta-chip { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; background: #f8fafc; color: #475569; font-size: 12px; line-height: 1.3; }
        .sub-item-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
        .sub-card-empty { padding: 18px; border-radius: 18px; border: 1px dashed #dbe4ee; background: #fbfdff; color: #64748b; font-size: 14px; }
        .empty-active-state { padding: 24px; border-radius: 20px; background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); border: 1px solid #e2e8f0; }
        .empty-active-state h3 { font-size: 22px; color: var(--primary); margin-bottom: 8px; }
        .empty-active-state p { color: #64748b; font-size: 15px; line-height: 1.55; margin-bottom: 14px; }
        .empty-active-meta { display: flex; flex-wrap: wrap; gap: 10px; }
        .empty-active-pill { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 13px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #f1f5f9; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-active { background: #dbeafe; color: #1e40af; }
        .badge-confirmed { background: #dcfce7; color: #166534; }
        .badge-live { background: #dbeafe; color: #1d4ed8; }
        .badge-upcoming { background: #eef2ff; color: #4338ca; }
        .badge-overdue { background: #fee2e2; color: #b91c1c; }
        .btn { padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.3s; display: inline-block; border: none; cursor: pointer; }
        .btn-accent { background: #1f6f78; color: white; border-radius: 999px; padding: 12px 18px; }
        .btn-outline { border: 1px solid #e2e8f0; color: #64748b; }
        @media (max-width: 980px) {
            .dual-active-grid { grid-template-columns: 1fr; }
            .sub-item { grid-template-columns: 1fr; }
            .sub-item-actions { align-items: stretch; }
        }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>

<div class="main-content">
    <?php if ($flashMessage !== ''): ?>
        <div style="<?= $flashTone === 'error'
            ? 'background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;'
            : 'background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;'
        ?> padding:10px 12px; border-radius:10px; margin-bottom:18px; font-size:14px;">
            <?= htmlspecialchars($flashMessage) ?>
        </div>
    <?php endif; ?>

    <div class="header">
        <div><h1>Welcome, <?= htmlspecialchars($user_name) ?>!</h1></div>
        <a href="add_tool.php" class="btn btn-accent">+ List New Tool</a>
    </div>

    <div class="section-heading" style="margin-top: 0;">
        <div>
            <span class="section-label"><span class="section-label-dot"></span>Active Rentals</span>
            <h2>Live Rentals Right Now</h2>
            <p>This section only shows rentals that are currently live right now.</p>
        </div>
    </div>

    <div class="card">
        <?php if (!$has_live_rentals): ?>
            <div class="empty-active-state">
                <h3>No live rentals right now</h3>
                <p>You do not currently have a tool out on rent, and you are not currently renting a tool from another owner.</p>
                <div class="empty-active-meta">
                    <?php if (count($incoming_requests) > 0): ?>
                        <span class="empty-active-pill"><?= count($incoming_requests) ?> request(s) waiting on your tools</span>
                    <?php endif; ?>
                    <?php if ($renter_pending_count > 0): ?>
                        <span class="empty-active-pill"><?= $renter_pending_count ?> rental request(s) waiting for approval or payment</span>
                    <?php endif; ?>
                    <?php if (count($return_requests) > 0): ?>
                        <span class="empty-active-pill"><?= count($return_requests) ?> return request(s) waiting for review</span>
                    <?php endif; ?>
                    <?php if ((int)$earnings_summary['active_owner_bookings'] > 0): ?>
                        <span class="empty-active-pill"><?= (int)$earnings_summary['active_owner_bookings'] ?> paid booking(s) still in progress</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="dual-active-grid" style="margin-bottom:0;">
                <div class="sub-card">
                    <div class="sub-card-header">
                        <div>
                            <h3>You Are Renting</h3>
                            <p>These are tools you currently have on rent from other owners.</p>
                        </div>
                        <span class="sub-card-count"><?= count($active_rented_tools) ?></span>
                    </div>
                    <?php if (empty($active_rented_tools)): ?>
                        <div class="sub-card-empty">You do not have any active rented tools right now.</div>
                    <?php else: ?>
                        <div class="sub-list">
                            <?php foreach ($active_rented_tools as $rental): ?>
                                <?php $status = toolshare_active_rental_status($rental); ?>
                                <div class="sub-item">
                                    <div class="sub-item-main">
                                        <div class="sub-item-top">
                                            <div class="sub-item-title"><?= htmlspecialchars($rental['title']) ?></div>
                                            <span class="badge <?= $status['class'] ?>"><?= htmlspecialchars($status['label']) ?></span>
                                            <span class="sub-item-booking">Booking #<?= (int)$rental['id'] ?></span>
                                        </div>
                                        <div class="sub-item-meta-row">
                                            <span class="sub-item-meta-chip">Owner: <?= htmlspecialchars($rental['owner_name']) ?></span>
                                            <span class="sub-item-meta-chip">Pickup: <?= date('M d, Y h:i A', strtotime($rental['pick_up_datetime'])) ?></span>
                                            <span class="sub-item-meta-chip">Drop-off: <?= date('M d, Y h:i A', strtotime($rental['drop_off_datetime'])) ?></span>
                                            <?php if ($rental['returned_at'] !== null): ?>
                                                <span class="sub-item-meta-chip">Return submitted: <?= date('M d, Y h:i A', strtotime($rental['returned_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="sub-item-actions">
                                        <?php if ($rental['returned_at'] === null && strtotime($rental['drop_off_datetime']) >= time()): ?>
                                            <a href="request_extension.php?booking_id=<?= (int)$rental['id'] ?>" class="btn" style="background:#1a3654; color:white;">Request Extension</a>
                                        <?php endif; ?>
                                        <?php if ($rental['returned_at'] === null && strtotime($rental['pick_up_datetime']) <= time()): ?>
                                            <form action="return_tool.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="booking_id" value="<?= (int)$rental['id'] ?>">
                                                <button type="submit" class="btn" style="background:#1f6f78; color:white;">Return Tool</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="chat.php?tool_id=<?= (int)$rental['tool_id'] ?>&receiver_id=<?= (int)$rental['owner_id'] ?>" class="btn btn-outline">Message Owner</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sub-card">
                    <div class="sub-card-header">
                        <div>
                            <h3>You Are the Owner</h3>
                            <p>These are your tools that are currently rented out to other customers.</p>
                        </div>
                        <span class="sub-card-count"><?= count($owner_active_rentals) ?></span>
                    </div>
                    <?php if (empty($owner_active_rentals)): ?>
                        <div class="sub-card-empty">None of your tools are currently rented out.</div>
                    <?php else: ?>
                        <div class="sub-list">
                            <?php foreach ($owner_active_rentals as $rental): ?>
                                <?php $status = toolshare_active_rental_status($rental); ?>
                                <div class="sub-item">
                                    <div class="sub-item-main">
                                        <div class="sub-item-top">
                                            <div class="sub-item-title"><?= htmlspecialchars($rental['title']) ?></div>
                                            <span class="badge <?= $status['class'] ?>"><?= htmlspecialchars($status['label']) ?></span>
                                            <span class="sub-item-booking">Booking #<?= (int)$rental['id'] ?></span>
                                        </div>
                                        <div class="sub-item-meta-row">
                                            <span class="sub-item-meta-chip">Renter: <?= htmlspecialchars($rental['renter_name']) ?></span>
                                            <span class="sub-item-meta-chip">Pickup: <?= date('M d, Y h:i A', strtotime($rental['pick_up_datetime'])) ?></span>
                                            <span class="sub-item-meta-chip">Drop-off: <?= date('M d, Y h:i A', strtotime($rental['drop_off_datetime'])) ?></span>
                                            <?php if ($rental['returned_at'] !== null): ?>
                                                <span class="sub-item-meta-chip">Return submitted: <?= date('M d, Y h:i A', strtotime($rental['returned_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="sub-item-actions">
                                        <a href="chat.php?tool_id=<?= (int)$rental['tool_id'] ?>&receiver_id=<?= (int)$rental['renter_id'] ?>" class="btn btn-outline">Message Renter</a>
                                        <?php if ($rental['returned_at'] !== null): ?>
                                            <a href="update_booking.php?id=<?= (int)$rental['id'] ?>&action=approve_return" class="btn" style="background:#059669; color:white;">Review Return</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="section-heading">
        <div>
            <span class="section-label"><span class="section-label-dot"></span>Lending Dashboard</span>
            <h2 id="profile">Tools You Listed for Others</h2>
            <p>Everything in this section is about tools you own and rent out to other customers.</p>
        </div>
    </div>

    <div class="stats-grid role-stats">
        <div class="stat-card"><h4>Tools I Listed</h4><p><?= count($my_tools) ?></p></div>
        <div class="stat-card"><h4>Booking Requests for My Tools</h4><p><?= count($incoming_requests) ?></p></div>
        <a href="active_rentals.php" class="stat-link">
            <div class="stat-card"><h4>Tools I Lent Out</h4><p><?= count($current_rentals) ?></p></div>
        </a>
        <a href="active_rentals.php" class="stat-link">
            <div class="stat-card"><h4>Returns Waiting on Me</h4><p><?= count($return_requests) ?></p></div>
        </a>
    </div>

    <div class="card">
        <div class="card-header"><h3>Requests for My Tools</h3></div>
        <table>
            <thead><tr><th>Renter</th><th>Tool</th><th>Pickup</th><th>Drop-off</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($incoming_requests as $req): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($req['renter_name']) ?></strong></td>
                    <td><?= htmlspecialchars($req['title']) ?></td>
                    <td><?= date('M d, Y h:i A', strtotime($req['pick_up_datetime'])) ?></td>
                    <td><?= date('M d, Y h:i A', strtotime($req['drop_off_datetime'])) ?></td>
                    <td>
                        <a href="update_booking.php?id=<?= $req['id'] ?>&action=confirm" style="color: #059669; font-weight: bold; margin-right: 15px;">Approve</a>
                        <a href="update_booking.php?id=<?= $req['id'] ?>&action=cancel" style="color: #dc2626;">Decline</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><h3>Extension Requests on My Tools</h3></div>
        <?php if (empty($extension_requests)): ?>
            <p style="color:#64748b;">No pending extension requests.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Renter</th><th>Tool</th><th>Current Drop-off</th><th>Requested Drop-off</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($extension_requests as $ext): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ext['renter_name']) ?></strong></td>
                        <td><?= htmlspecialchars($ext['title']) ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($ext['current_dropoff'])) ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($ext['requested_dropoff_datetime'])) ?></td>
                        <td>
                            <a href="update_booking.php?action=approve_extension&ext_id=<?= (int)$ext['id'] ?>" style="color: #059669; font-weight: bold; margin-right: 15px;">Approve</a>
                            <a href="update_booking.php?action=decline_extension&ext_id=<?= (int)$ext['id'] ?>" style="color: #dc2626;">Decline</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h3>Returned Tools Waiting for My Review</h3></div>
        <?php if (empty($return_requests)): ?>
            <p style="color:#64748b;">No renter return requests waiting for action.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Tool</th><th>Renter</th><th>Returned</th><th>Deposit</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($return_requests as $request): ?>
                    <?php
                        $isResolvedDispute = in_array($request['dispute_status'], ['resolved', 'rejected'], true);
                        $ownerNeedsAction = $request['return_reviewed_at'] === null && $request['dispute_status'] === 'none';
                        $returnState = $request['dispute_status'] !== 'none' && !$isResolvedDispute
                            ? 'Under Dispute'
                            : ($request['return_reviewed_at'] !== null ? 'Reviewed' : 'Awaiting Review');
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($request['title']) ?></strong></td>
                        <td><?= htmlspecialchars($request['renter_name']) ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($request['returned_at'])) ?></td>
                        <td>$<?= number_format((float)$request['security_deposit'], 2) ?></td>
                        <td><span class="badge <?= $ownerNeedsAction ? 'badge-pending' : 'badge-active' ?>"><?= htmlspecialchars($returnState) ?></span></td>
                        <td>
                            <?php if ($ownerNeedsAction): ?>
                                <a href="update_booking.php?id=<?= (int)$request['id'] ?>&action=approve_return" style="color:#059669; font-weight:bold; margin-right: 15px;">Approve Return</a>
                                <a href="raise_dispute.php?booking_id=<?= (int)$request['id'] ?>&mode=owner" style="color:#dc2626;">Raise Dispute</a>
                            <?php elseif ($request['dispute_status'] !== 'none' && !$isResolvedDispute): ?>
                                <span style="color:#64748b; font-size:12px;">Under admin review</span>
                            <?php else: ?>
                                <span style="color:#64748b; font-size:12px;"><?= htmlspecialchars($returnState) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="earnings" class="card">
        <div class="card-header">
            <h3>Earnings from My Listed Tools</h3>
            <a href="my_earnings.php" class="btn btn-outline">Open Full Earnings Page</a>
        </div>
        <div class="stats-grid" style="margin-bottom: 24px;">
            <div class="stat-card">
                <h4>Settled Booking Revenue</h4>
                <p>$<?= number_format((float)$earnings_summary['settled_revenue'], 2) ?></p>
            </div>
            <div class="stat-card">
                <h4>Total Earnings</h4>
                <p>$<?= number_format((float)$earnings_summary['released_earnings'], 2) ?></p>
            </div>
            <div class="stat-card">
                <h4>Completed Bookings</h4>
                <p><?= number_format((int)$earnings_summary['completed_bookings']) ?></p>
            </div>
        </div>
        <?php if ((int)$earnings_summary['active_owner_bookings'] > 0): ?>
            <div style="margin-bottom:18px; color:#64748b; font-size:14px;">
                <?= (int)$earnings_summary['active_owner_bookings'] ?> active paid booking(s) are still in progress and are not counted in total earnings yet.
            </div>
        <?php endif; ?>
        <?php if (empty($earnings_rows)): ?>
            <p style="color:#64748b;">No released earnings yet. Completed rentals will appear here after the return is approved and the booking is settled.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Booking</th><th>Tool</th><th>Renter</th><th>Status</th><th>Settled Gross</th><th>Owner Fee</th><th>Deposit Kept</th><th>Total Received</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($earnings_rows as $earning): ?>
                    <tr>
                        <td>#<?= (int)$earning['id'] ?><br><span style="font-size:12px; color:#64748b;"><?= date('M d, Y', strtotime($earning['pick_up_datetime'])) ?></span></td>
                        <td><strong><?= htmlspecialchars($earning['title']) ?></strong></td>
                        <td><?= htmlspecialchars($earning['renter_name']) ?></td>
                        <td><span class="badge badge-<?= $earning['status'] === 'completed' ? 'confirmed' : 'active' ?>"><?= htmlspecialchars(ucfirst((string)$earning['status'])) ?></span></td>
                        <td>$<?= number_format((float)$earning['total_price'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['owner_fee'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['deposit_kept'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['owner_total_received'], 2) ?></td>
                        <td>
                            <button type="button" data-download-url="owner_earnings_receipt_pdf.php?booking_id=<?= (int)$earning['id'] ?>" class="btn js-direct-download" style="background:#15324a; color:#fff; border:none; cursor:pointer;">Download Statement</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section-heading">
        <div>
            <span class="section-label section-label-renting"><span class="section-label-dot"></span>Renting Dashboard</span>
            <h2>Tools You Booked from Others</h2>
            <p>Everything below is about tools you rented from other owners.</p>
        </div>
    </div>

    <div class="stats-grid role-stats">
        <div class="stat-card"><h4>Rental Requests I Sent</h4><p><?= count($my_outgoing_requests) ?></p></div>
        <div class="stat-card"><h4>Tools I Am Renting Now</h4><p><?= $renter_active_count ?></p></div>
        <div class="stat-card"><h4>Waiting for Approval or Payment</h4><p><?= $renter_pending_count ?></p></div>
        <div class="stat-card"><h4>My Completed Rentals</h4><p><?= $renter_completed_count ?></p></div>
    </div>

    <div class="card">
        <div class="card-header"><h3>My Rented Tools</h3></div>
        <table>
            <thead><tr><th>Tool</th><th>Owner</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($my_outgoing_requests as $req): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($req['title']) ?></strong></td>
                    <td><?= htmlspecialchars($req['owner_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span>
                        <?php if ($req['returned_at'] !== null && $req['status'] === 'paid'): ?>
                            <div style="font-size:12px; color:#64748b; margin-top:4px;">Return submitted on <?= date('M d, Y h:i A', strtotime($req['returned_at'])) ?></div>
                        <?php elseif (!empty($req['pickup_confirmed_at']) && $req['status'] === 'paid'): ?>
                            <div style="font-size:12px; color:#166534; margin-top:4px;">Pickup confirmed on <?= date('M d, Y h:i A', strtotime($req['pickup_confirmed_at'])) ?></div>
                        <?php elseif ($req['dispute_status'] !== 'none' && !in_array($req['dispute_status'], ['resolved', 'rejected'], true)): ?>
                            <div style="font-size:12px; color:#b45309; margin-top:4px;">This dispute has been escalated to the operations team for review.</div>
                            <div style="font-size:12px; color:#64748b; margin-top:4px;">We may contact you by email or phone if more information is needed before a decision is made.</div>
                        <?php elseif ($req['dispute_status'] === 'resolved'): ?>
                            <div style="font-size:12px; color:#166534; margin-top:4px;">Operations completed the review and closed the dispute.</div>
                            <?php if (($req['dispute_initiated_by'] ?? 'owner') === 'renter'): ?>
                                <?php if ($req['dispute_admin_decision'] === 'full_refund'): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: full renter-side refund was approved.</div>
                                <?php elseif ($req['dispute_admin_decision'] === 'partial_refund'): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: partial refund approved after review.</div>
                                <?php elseif ($req['dispute_admin_decision'] === 'replacement_or_manual_resolution'): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: operations moved the case to manual resolution.</div>
                                <?php elseif ($req['dispute_admin_decision'] === 'deny'): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: renter claim was denied after review.</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($req['dispute_admin_decision'] === 'full_refund'): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: full deposit refund approved.</div>
                                <?php elseif ($req['dispute_admin_decision'] === 'partial_deduction'): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: partial deposit deduction was applied after review.</div>
                                <?php elseif ($req['dispute_admin_decision'] === 'full_forfeit'): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: full deposit forfeiture was applied after review.</div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($req['dispute_resolution_summary'])): ?>
                                <div style="font-size:12px; color:#64748b; margin-top:4px;">Summary: <?= htmlspecialchars($req['dispute_resolution_summary']) ?></div>
                            <?php endif; ?>
                        <?php elseif ($req['dispute_status'] === 'rejected'): ?>
                            <?php if (($req['dispute_initiated_by'] ?? 'owner') === 'renter'): ?>
                                <div style="font-size:12px; color:#166534; margin-top:4px;">Operations closed the renter claim without approving a refund action.</div>
                            <?php else: ?>
                                <div style="font-size:12px; color:#166534; margin-top:4px;">Operations closed the dispute in your favor.</div>
                                <div style="font-size:12px; color:#64748b; margin-top:4px;">Decision: full deposit refund released.</div>
                            <?php endif; ?>
                            <?php if (!empty($req['dispute_resolution_summary'])): ?>
                                <div style="font-size:12px; color:#64748b; margin-top:4px;">Summary: <?= htmlspecialchars($req['dispute_resolution_summary']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($req['status'] == 'confirmed'): ?>
                            <a href="checkout.php?booking_id=<?= (int)$req['id'] ?>" class="btn" style="background:#059669; color:white;">Pay Now</a>
                            <a href="chat.php?tool_id=<?= (int)$req['tool_id'] ?>&receiver_id=<?= (int)$req['owner_id'] ?>" class="btn btn-outline" style="margin-left:8px;">Message Owner</a>
                        <?php elseif ($req['status'] == 'paid' && $req['returned_at'] === null): ?>
                            <?php
                                $pickupTs = strtotime((string)$req['pick_up_datetime']);
                                $dropoffTs = strtotime((string)$req['drop_off_datetime']);
                                $pickupConfirmed = !empty($req['pickup_confirmed_at']);
                                $canReportProblem = $req['dispute_status'] === 'none'
                                    && !$pickupConfirmed
                                    && $pickupTs !== false
                                    && $dropoffTs !== false
                                    && time() >= ($pickupTs - 7200)
                                    && time() <= ($dropoffTs + 86400);
                            ?>
                            <?php if (strtotime($req['drop_off_datetime']) >= time()): ?>
                                <a href="request_extension.php?booking_id=<?= (int)$req['id'] ?>" class="btn" style="background:#1a3654; color:white;">Request Extension</a>
                            <?php endif; ?>
                            <?php if (!$pickupConfirmed && strtotime($req['pick_up_datetime']) <= time()): ?>
                                <form action="confirm_pickup.php" method="POST" style="display:inline-block; margin-left:8px;">
                                    <input type="hidden" name="booking_id" value="<?= (int)$req['id'] ?>">
                                    <button type="submit" class="btn" style="background:#2563eb; color:white;">Confirm Pickup</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canReportProblem): ?>
                                <a href="raise_dispute.php?booking_id=<?= (int)$req['id'] ?>&mode=renter" class="btn" style="background:#b45309; color:white; margin-left:8px;">Report a Problem</a>
                            <?php endif; ?>
                            <?php if (strtotime($req['pick_up_datetime']) <= time()): ?>
                                <form action="return_tool.php" method="POST" style="display:inline-block; margin-left:8px;">
                                    <input type="hidden" name="booking_id" value="<?= (int)$req['id'] ?>">
                                    <button type="submit" class="btn" style="background:#1f6f78; color:white;">Return Tool</button>
                                </form>
                            <?php endif; ?>
                            <a href="chat.php?tool_id=<?= (int)$req['tool_id'] ?>&receiver_id=<?= (int)$req['owner_id'] ?>" class="btn btn-outline" style="margin-left:8px;">Message Owner</a>
                            <button type="button" data-download-url="renter_receipt_pdf.php?booking_id=<?= (int)$req['id'] ?>" class="btn js-direct-download" style="background:#15324a; color:white; margin-left:8px; border:none; cursor:pointer;">Download Receipt</button>
                        <?php elseif ($req['status'] == 'paid' && $req['returned_at'] !== null): ?>
                            <?php if ($req['dispute_status'] !== 'none' && !in_array($req['dispute_status'], ['resolved', 'rejected'], true)): ?>
                                <span style="color:#b45309; font-size: 12px;">Dispute under operations review</span>
                            <?php else: ?>
                                <span style="color:#64748b; font-size: 12px;">Waiting for owner review</span>
                            <?php endif; ?>
                            <a href="chat.php?tool_id=<?= (int)$req['tool_id'] ?>&receiver_id=<?= (int)$req['owner_id'] ?>" class="btn btn-outline" style="margin-left:8px;">Message Owner</a>
                            <button type="button" data-download-url="renter_receipt_pdf.php?booking_id=<?= (int)$req['id'] ?>" class="btn js-direct-download" style="background:#15324a; color:white; margin-left:8px; border:none; cursor:pointer;">Download Receipt</button>
                        <?php elseif ($req['status'] == 'completed'): ?>
                            <button type="button" data-download-url="renter_receipt_pdf.php?booking_id=<?= (int)$req['id'] ?>" class="btn js-direct-download" style="background:#15324a; color:white; border:none; cursor:pointer;">Download Receipt</button>
                        <?php else: ?>
                            <span style="color:#64748b; font-size: 12px;"><?= ucfirst($req['status']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><h3>Recent Messages</h3></div>
        <table>
            <thead><tr><th>Contact</th><th>Tool</th><th>Message</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($recent_messages)): ?>
                <tr>
                    <td colspan="4" style="color:#64748b;">No recent messages or support replies yet.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($recent_messages as $message): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string)$message['contact_name']) ?></strong></td>
                    <td><?= htmlspecialchars((string)$message['context']) ?></td>
                    <td style="color:#64748b; font-style:italic;">"<?= htmlspecialchars(strlen((string)$message['preview']) > 40 ? substr((string)$message['preview'], 0, 40) . '...' : (string)$message['preview']) ?>"</td>
                    <td><a href="<?= htmlspecialchars((string)$message['href']) ?>" class="btn btn-outline"><?= $message['thread_type'] === 'support' ? 'Open Support Chat' : 'Open Chat' ?></a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><h3>My Tool Listings</h3></div>
        <table>
            <thead><tr><th>Tool</th><th>Rate</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($my_tools as $tool): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($tool['title']) ?></strong></td>
                    <td>$<?= number_format($tool['price_daily'], 2) ?>/day</td>
                    <td>
                        <?php if (!empty($tool['active_dropoff'])): ?>
                            <span class="badge" style="background:#fee2e2; color:#991b1b;">Unavailable</span>
                            <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                <?= (int)$tool['active_duration_count'] . " " . htmlspecialchars($tool['active_duration_type']) ?>(s),
                                until <?= date('M d, Y h:i A', strtotime($tool['active_dropoff'])) ?>
                            </div>
                        <?php else: ?>
                            <span class="badge badge-active">Available</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit_tool.php?id=<?= $tool['id'] ?>" style="margin-right: 10px; color: var(--primary);">Edit</a>
                        <a href="delete_tool.php?id=<?= $tool['id'] ?>" style="color: #dc2626;" onclick="return confirm('Delete this?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
        window.alert('Unable to download the file right now.');
    } finally {
        button.textContent = originalText;
        button.disabled = false;
    }
});
</script>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
