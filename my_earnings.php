<?php
session_start();
require 'config/db.php';
require 'includes/booking_extensions_bootstrap.php';
require 'includes/returns_bootstrap.php';
require 'includes/booking_charges_bootstrap.php';
require 'includes/site_chrome.php';
require 'includes/auth.php';

toolshare_require_user();
toolshare_bootstrap_booking_charges($pdo);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$stmt_released = $pdo->prepare("
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
");
$stmt_released->execute([$user_id]);
$released_rows = $stmt_released->fetchAll();

$stmt_pending = $pdo->prepare("
    SELECT
        b.id,
        t.title,
        u.full_name AS renter_name,
        b.status,
        b.total_price,
        b.pick_up_datetime,
        b.drop_off_datetime,
        COALESCE(bc.owner_platform_fee_amount, ROUND(b.total_price * 0.03, 2)) AS owner_fee,
        COALESCE(bc.owner_net_payout, ROUND(b.total_price * 0.97, 2)) AS estimated_owner_payout
    FROM bookings b
    JOIN tools t ON b.tool_id = t.id
    JOIN users u ON b.renter_id = u.id
    LEFT JOIN booking_charges bc ON bc.booking_id = b.id
    WHERE b.owner_id = ?
      AND b.status = 'paid'
    ORDER BY b.drop_off_datetime ASC, b.id DESC
");
$stmt_pending->execute([$user_id]);
$pending_rows = $stmt_pending->fetchAll();

$stmt_summary = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_price ELSE 0 END), 0) AS settled_revenue,
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN COALESCE(bc.owner_net_payout, b.total_price * 0.97) + COALESCE(bc.deposit_deduction_amount, 0) ELSE 0 END), 0) AS released_earnings,
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_bookings,
        COALESCE(SUM(CASE WHEN b.status = 'paid' THEN COALESCE(bc.owner_net_payout, b.total_price * 0.97) ELSE 0 END), 0) AS pending_earnings,
        COALESCE(SUM(CASE WHEN b.status = 'paid' THEN 1 ELSE 0 END), 0) AS active_owner_bookings
    FROM bookings b
    LEFT JOIN booking_charges bc ON bc.booking_id = b.id
    WHERE b.owner_id = ?
");
$stmt_summary->execute([$user_id]);
$earnings_summary = $stmt_summary->fetch();

$released_by_month = [];
$released_by_year = [];
foreach ($released_rows as $row) {
    $timestamp = strtotime($row['pick_up_datetime']);
    $monthKey = date('Y-m', $timestamp);
    $yearKey = date('Y', $timestamp);
    $amount = (float) $row['owner_total_received'];

    if (!isset($released_by_month[$monthKey])) {
        $released_by_month[$monthKey] = 0.0;
    }
    if (!isset($released_by_year[$yearKey])) {
        $released_by_year[$yearKey] = 0.0;
    }

    $released_by_month[$monthKey] += $amount;
    $released_by_year[$yearKey] += $amount;
}

$chart_datasets = [];

$chart_points_6m = [];
$chart_max_6m = 0.0;
$start_6m = new DateTime('first day of this month');
$start_6m->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $key = $start_6m->format('Y-m');
    $amount = $released_by_month[$key] ?? 0.0;
    $chart_points_6m[] = [
        'label' => $start_6m->format('M'),
        'sub_label' => $start_6m->format('Y'),
        'amount' => $amount,
    ];
    if ($amount > $chart_max_6m) {
        $chart_max_6m = $amount;
    }
    $start_6m->modify('+1 month');
}
$chart_datasets['6m'] = [
    'title' => 'Last 6 Months',
    'subtitle' => 'Monthly released earnings with empty months included.',
    'summaryLabel' => 'Latest Month',
    'summaryValue' => !empty($chart_points_6m) ? end($chart_points_6m)['amount'] : 0,
    'summaryCopy' => 'This chart groups your released earnings into time periods so longer histories stay easy to read.',
    'max' => $chart_max_6m,
    'points' => $chart_points_6m,
];

$chart_points_12m = [];
$chart_max_12m = 0.0;
$start_12m = new DateTime('first day of this month');
$start_12m->modify('-11 months');
for ($i = 0; $i < 12; $i++) {
    $key = $start_12m->format('Y-m');
    $amount = $released_by_month[$key] ?? 0.0;
    $chart_points_12m[] = [
        'label' => $start_12m->format('M'),
        'sub_label' => $start_12m->format('Y'),
        'amount' => $amount,
    ];
    if ($amount > $chart_max_12m) {
        $chart_max_12m = $amount;
    }
    $start_12m->modify('+1 month');
}
$chart_datasets['12m'] = [
    'title' => 'Last 12 Months',
    'subtitle' => 'Monthly released earnings with empty months included.',
    'summaryLabel' => 'Latest Month',
    'summaryValue' => !empty($chart_points_12m) ? end($chart_points_12m)['amount'] : 0,
    'summaryCopy' => 'This chart groups your released earnings into time periods so longer histories stay easy to read.',
    'max' => $chart_max_12m,
    'points' => $chart_points_12m,
];

$chart_points_yearly = [];
$chart_max_yearly = 0.0;
if (!empty($released_by_year)) {
    ksort($released_by_year);
    foreach ($released_by_year as $year => $amount) {
        $chart_points_yearly[] = [
            'label' => $year,
            'sub_label' => 'Released',
            'amount' => $amount,
        ];
        if ($amount > $chart_max_yearly) {
            $chart_max_yearly = $amount;
        }
    }
}
$chart_datasets['yearly'] = [
    'title' => 'Earnings by Year',
    'subtitle' => 'Yearly totals from completed bookings.',
    'summaryLabel' => 'Latest Year',
    'summaryValue' => !empty($chart_points_yearly) ? end($chart_points_yearly)['amount'] : 0,
    'summaryCopy' => 'Use yearly view when your earnings history spans multiple calendar years.',
    'max' => $chart_max_yearly,
    'points' => $chart_points_yearly,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Earnings | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root { --primary: #15324a; --accent: #1f6f78; --bg: #f8fafc; --text: #1e293b; --tone-blue: #2563eb; --tone-blue-soft: #eff6ff; --tone-blue-line: #bfdbfe; --airbnb-ink: #222222; --airbnb-muted: #6a6a6a; --airbnb-line: #ebebeb; --airbnb-surface: #ffffff; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(180deg, #fdfdfd 0%, #f6f7fb 100%); color: var(--text); }
        .main-content { width: min(1320px, 94%); margin: 0 auto; padding: 20px 0 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 24px; }
        .page-header h1 { font-size: 32px; color: var(--airbnb-ink); letter-spacing: -0.02em; }
        .page-header p { color: var(--airbnb-muted); margin-top: 10px; max-width: 760px; line-height: 1.55; font-size: 15px; }
        .btn { padding: 10px 16px; border-radius: 999px; text-decoration: none; font-weight: 700; font-size: 13px; display: inline-block; }
        .btn-outline { border: 1px solid var(--airbnb-line); color: var(--airbnb-ink); background: rgba(255,255,255,0.95); box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: rgba(255,255,255,0.92); padding: 22px; border-radius: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05); border: 1px solid rgba(255,255,255,0.95); }
        .stat-card h4 { font-size: 12px; text-transform: uppercase; color: #7a8699; margin-bottom: 8px; letter-spacing: 0.04em; display: flex; align-items: center; gap: 8px; }
        .stat-card p { font-size: 30px; font-weight: 800; color: var(--airbnb-ink); }
        .stat-help { position: relative; display: inline-flex; align-items: center; }
        .stat-help button { width: 18px; height: 18px; border-radius: 50%; border: 1px solid #cbd5e1; background: #fff; color: #64748b; font-size: 11px; font-weight: 700; line-height: 1; cursor: help; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
        .stat-help button:focus-visible { outline: 2px solid #93c5fd; outline-offset: 2px; }
        .stat-help-text { position: absolute; left: 50%; top: calc(100% + 10px); transform: translateX(-50%); width: 220px; padding: 10px 12px; border-radius: 12px; background: #15324a; color: #fff; font-size: 12px; font-weight: 500; line-height: 1.45; letter-spacing: normal; text-transform: none; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18); opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 0.18s ease, visibility 0.18s ease; z-index: 5; }
        .stat-help-text::before { content: ""; position: absolute; left: 50%; top: -6px; transform: translateX(-50%) rotate(45deg); width: 12px; height: 12px; background: #15324a; }
        .stat-help:hover .stat-help-text,
        .stat-help:focus-within .stat-help-text { opacity: 1; visibility: visible; }
        .card { background: rgba(255,255,255,0.92); border-radius: 28px; padding: 25px; margin-bottom: 26px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.05); border: 1px solid rgba(255,255,255,0.95); overflow-x: auto; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 12px; }
        .card-header h3 { font-size: 20px; color: var(--airbnb-ink); letter-spacing: -0.01em; }
        .card-header p { color: var(--airbnb-muted); font-size: 14px; }
        .info-banner { margin-bottom: 26px; border-radius: 28px; background:
            linear-gradient(135deg, #f8fbff 0%, #eef6ff 52%, #f9fcff 100%);
            color: var(--airbnb-ink); padding: 28px; border: 1px solid var(--tone-blue-line); box-shadow: 0 14px 34px rgba(37, 99, 235, 0.08); position: relative; overflow: hidden; }
        .info-banner::after { content: ""; position: absolute; inset: auto -40px -60px auto; width: 180px; height: 180px; background: radial-gradient(circle, rgba(37,99,235,0.12) 0%, rgba(37,99,235,0) 70%); pointer-events: none; }
        .info-banner-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .info-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,0.96); border: 1px solid var(--tone-blue-line); color: #1d4ed8; font-size: 12px; font-weight: 700; letter-spacing: 0.02em; }
        .info-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--tone-blue); }
        .info-banner h2 { font-size: 26px; margin-bottom: 10px; letter-spacing: -0.02em; }
        .info-banner p { line-height: 1.65; max-width: 820px; color: #4b5563; font-size: 15px; }
        .info-points { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 20px; }
        .info-point { background: rgba(255,255,255,0.86); border: 1px solid #dbeafe; border-radius: 18px; padding: 16px 18px; }
        .info-point strong { display: block; color: var(--airbnb-ink); font-size: 14px; margin-bottom: 5px; }
        .info-point span { color: var(--airbnb-muted); font-size: 14px; line-height: 1.5; }
        .chart-card { padding-bottom: 18px; }
        .chart-toolbar { display: inline-flex; align-items: center; gap: 6px; padding: 4px; border-radius: 999px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .chart-toggle { border: none; background: transparent; color: #64748b; padding: 8px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .chart-toggle.is-active { background: #15324a; color: #fff; }
        .chart-shell { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 22px; align-items: end; }
        .chart-summary { padding-right: 8px; }
        .chart-summary-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #7a8699; margin-bottom: 10px; }
        .chart-summary-value { font-size: 34px; font-weight: 800; color: var(--airbnb-ink); line-height: 1; }
        .chart-summary-copy { color: var(--airbnb-muted); font-size: 14px; line-height: 1.55; margin-top: 10px; }
        .earnings-chart { height: 240px; display: flex; align-items: end; gap: 16px; padding: 18px 8px 8px; border-left: 1px solid #e5edf8; border-bottom: 1px solid #e5edf8; }
        .chart-bar-group { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: end; gap: 10px; }
        .chart-bar-wrap { width: 100%; max-width: 72px; height: 170px; display: flex; align-items: end; justify-content: center; }
        .chart-bar { width: 100%; border-radius: 18px 18px 10px 10px; background: linear-gradient(180deg, #5aa7ff 0%, #2563eb 100%); box-shadow: 0 10px 22px rgba(37, 99, 235, 0.18); position: relative; min-height: 12px; }
        .chart-bar-value { position: absolute; top: -28px; left: 50%; transform: translateX(-50%); color: #1e3a8a; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .chart-bar-label { text-align: center; }
        .chart-bar-label strong { display: block; font-size: 12px; color: var(--airbnb-ink); }
        .chart-bar-label span { display: block; font-size: 11px; color: #7a8699; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 88px; }
        table { width: 100%; border-collapse: collapse; min-width: 920px; }
        th { text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #f1f5f9; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: top; }
        .history-preview-inline { margin-bottom: 16px; padding: 14px 16px; border-radius: 16px; background: #f8fbff; border: 1px solid #dbeafe; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .history-preview-inline strong { color: var(--airbnb-ink); font-size: 14px; }
        .history-preview-inline span { color: var(--airbnb-muted); font-size: 14px; line-height: 1.5; }
        .history-preview-inline-amount { color: #1d4ed8; font-size: 18px; font-weight: 800; white-space: nowrap; }
        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .badge-confirmed { background: #dcfce7; color: #166534; }
        .badge-active { background: #dbeafe; color: #1e40af; }
        .muted { color: #64748b; font-size: 13px; }
        .download-btn { background:#15324a; color:#fff; border:none; cursor:pointer; padding: 10px 14px; border-radius: 10px; font-weight: 700; }
        @media (max-width: 720px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-header h1 { font-size: 28px; }
            .info-banner { padding: 22px; border-radius: 22px; }
            .info-banner h2 { font-size: 22px; }
            .info-points { grid-template-columns: 1fr; }
            .stat-help-text { left: 0; transform: none; }
            .stat-help-text::before { left: 14px; transform: rotate(45deg); }
            .chart-shell { grid-template-columns: 1fr; }
            .earnings-chart { height: 210px; gap: 12px; overflow-x: auto; padding-top: 26px; }
            .chart-bar-group { min-width: 72px; }
            .history-preview-inline { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1><?= htmlspecialchars($user_name) ?>'s Earnings</h1>
            <p>Your earnings are recorded when a rental you own is fully completed. Active paid rentals appear as pending until the return is approved and the booking is settled.</p>
        </div>
        <a href="dashboard.php#earnings" class="btn btn-outline">Back to Dashboard Summary</a>
    </div>

    <div class="info-banner">
        <div class="info-banner-top">
            <span class="info-pill"><span class="info-dot"></span>Earnings overview</span>
        </div>
        <h2>A clearer view of what you’ve earned</h2>
        <p>Released earnings include the owner payout for completed rentals, plus any deposit amount kept after review. Active paid rentals stay separate until the return is approved and the booking is settled.</p>
        <div class="info-points">
            <div class="info-point">
                <strong>Released earnings</strong>
                <span>Finalized amounts from completed bookings that already count toward your total.</span>
            </div>
            <div class="info-point">
                <strong>Pending earnings</strong>
                <span>Expected owner payouts for active paid rentals that are still in progress.</span>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>Settled Booking Revenue
                <span class="stat-help">
                    <button type="button" aria-label="What is settled booking revenue?">?</button>
                    <span class="stat-help-text">The total booking amount from rentals that are fully finished and settled.</span>
                </span>
            </h4>
            <p>$<?= number_format((float)$earnings_summary['settled_revenue'], 2) ?></p>
        </div>
        <div class="stat-card">
            <h4>Released Earnings
                <span class="stat-help">
                    <button type="button" aria-label="What are released earnings?">?</button>
                    <span class="stat-help-text">Money already finalized for you from completed rentals.</span>
                </span>
            </h4>
            <p>$<?= number_format((float)$earnings_summary['released_earnings'], 2) ?></p>
        </div>
        <div class="stat-card">
            <h4>Pending Earnings
                <span class="stat-help">
                    <button type="button" aria-label="What are pending earnings?">?</button>
                    <span class="stat-help-text">Expected earnings from active paid rentals that are not finished yet.</span>
                </span>
            </h4>
            <p>$<?= number_format((float)$earnings_summary['pending_earnings'], 2) ?></p>
        </div>
        <div class="stat-card">
            <h4>Completed Bookings
                <span class="stat-help">
                    <button type="button" aria-label="What are completed bookings?">?</button>
                    <span class="stat-help-text">The number of your rentals that have already been fully completed.</span>
                </span>
            </h4>
            <p><?= number_format((int)$earnings_summary['completed_bookings']) ?></p>
        </div>
    </div>

    <div class="card chart-card">
        <div class="card-header">
            <div>
                <h3 id="chart-title"><?= htmlspecialchars($chart_datasets['12m']['title']) ?></h3>
                <p id="chart-subtitle"><?= htmlspecialchars($chart_datasets['12m']['subtitle']) ?></p>
            </div>
            <div class="chart-toolbar" aria-label="Chart range">
                <button type="button" class="chart-toggle" data-chart-view="6m">6M</button>
                <button type="button" class="chart-toggle is-active" data-chart-view="12m">12M</button>
                <button type="button" class="chart-toggle" data-chart-view="yearly">Yearly</button>
            </div>
        </div>
        <?php if (empty($released_rows)): ?>
            <p class="muted">Complete a rental to start seeing your earnings trend here.</p>
        <?php else: ?>
            <div class="chart-shell">
                <div class="chart-summary">
                    <div class="chart-summary-label" id="chart-summary-label"><?= htmlspecialchars($chart_datasets['12m']['summaryLabel']) ?></div>
                    <div class="chart-summary-value" id="chart-summary-value">$<?= number_format($chart_datasets['12m']['summaryValue'], 2) ?></div>
                    <p class="chart-summary-copy" id="chart-summary-copy"><?= htmlspecialchars($chart_datasets['12m']['summaryCopy']) ?></p>
                </div>
                <div class="earnings-chart" id="earnings-chart" aria-label="Earnings bar chart"></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3>Pending Payouts</h3>
                <p><?= (int)$earnings_summary['active_owner_bookings'] ?> active paid booking(s) are still in progress.</p>
            </div>
        </div>
        <?php if (empty($pending_rows)): ?>
            <p class="muted">No pending payouts right now. New paid bookings will appear here until they are completed.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Booking</th><th>Tool</th><th>Renter</th><th>Status</th><th>Pickup</th><th>Drop-off</th><th>Gross</th><th>Owner Fee</th><th>Estimated Payout</th></tr></thead>
                <tbody>
                    <?php foreach ($pending_rows as $earning): ?>
                    <tr>
                        <td>#<?= (int)$earning['id'] ?></td>
                        <td><strong><?= htmlspecialchars($earning['title']) ?></strong></td>
                        <td><?= htmlspecialchars($earning['renter_name']) ?></td>
                        <td><span class="badge badge-active"><?= htmlspecialchars(ucfirst((string)$earning['status'])) ?></span></td>
                        <td><?= date('M d, Y h:i A', strtotime($earning['pick_up_datetime'])) ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($earning['drop_off_datetime'])) ?></td>
                        <td>$<?= number_format((float)$earning['total_price'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['owner_fee'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['estimated_owner_payout'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3>Released Earnings History</h3>
                <p>These entries are finalized and already counted in your total earnings.</p>
            </div>
        </div>
        <?php if (empty($released_rows)): ?>
            <p class="muted">No released earnings yet. Completed rentals will appear here after the return is approved and the booking is settled.</p>
        <?php else: ?>
            <?php $history_preview = $released_rows[0]; ?>
            <div class="history-preview-inline">
                <span><strong>Latest payout:</strong> <?= htmlspecialchars($history_preview['title']) ?> rented by <?= htmlspecialchars($history_preview['renter_name']) ?> on <?= date('M d, Y', strtotime($history_preview['pick_up_datetime'])) ?>. Booking #<?= (int) $history_preview['id'] ?>.</span>
                <div class="history-preview-inline-amount">$<?= number_format((float) $history_preview['owner_total_received'], 2) ?></div>
            </div>
            <table>
                <thead><tr><th>Booking</th><th>Tool</th><th>Renter</th><th>Status</th><th>Settled Gross</th><th>Owner Fee</th><th>Deposit Kept</th><th>Total Received</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($released_rows as $earning): ?>
                    <tr>
                        <td>#<?= (int)$earning['id'] ?><br><span class="muted"><?= date('M d, Y', strtotime($earning['pick_up_datetime'])) ?></span></td>
                        <td><strong><?= htmlspecialchars($earning['title']) ?></strong></td>
                        <td><?= htmlspecialchars($earning['renter_name']) ?></td>
                        <td><span class="badge badge-confirmed"><?= htmlspecialchars(ucfirst((string)$earning['status'])) ?></span></td>
                        <td>$<?= number_format((float)$earning['total_price'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['owner_fee'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['deposit_kept'], 2) ?></td>
                        <td>$<?= number_format((float)$earning['owner_total_received'], 2) ?></td>
                        <td><button type="button" data-download-url="owner_earnings_receipt_pdf.php?booking_id=<?= (int)$earning['id'] ?>" class="download-btn js-direct-download">Download Statement</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
const chartDatasets = <?= json_encode($chart_datasets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function renderChart(view) {
    const dataset = chartDatasets[view];
    if (!dataset) return;

    const title = document.getElementById('chart-title');
    const subtitle = document.getElementById('chart-subtitle');
    const summaryLabel = document.getElementById('chart-summary-label');
    const summaryValue = document.getElementById('chart-summary-value');
    const summaryCopy = document.getElementById('chart-summary-copy');
    const chart = document.getElementById('earnings-chart');

    if (!title || !subtitle || !summaryLabel || !summaryValue || !summaryCopy || !chart) return;

    title.textContent = dataset.title;
    subtitle.textContent = dataset.subtitle;
    summaryLabel.textContent = dataset.summaryLabel;
    summaryValue.textContent = formatCurrency(dataset.summaryValue || 0);
    summaryCopy.textContent = dataset.summaryCopy;

    chart.innerHTML = '';
    dataset.points.forEach((point) => {
        const height = dataset.max > 0 ? Math.max(12, Math.round((point.amount / dataset.max) * 170)) : 12;
        const group = document.createElement('div');
        group.className = 'chart-bar-group';
        group.innerHTML = `
            <div class="chart-bar-wrap">
                <div class="chart-bar" style="height: ${height}px;">
                    <span class="chart-bar-value">${formatCurrency(point.amount)}</span>
                </div>
            </div>
            <div class="chart-bar-label">
                <strong>${point.label}</strong>
                <span>${point.sub_label}</span>
            </div>
        `;
        chart.appendChild(group);
    });

    document.querySelectorAll('[data-chart-view]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.chartView === view);
    });
}

document.querySelectorAll('[data-chart-view]').forEach((button) => {
    button.addEventListener('click', function () {
        renderChart(this.dataset.chartView);
    });
});

renderChart('12m');

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
