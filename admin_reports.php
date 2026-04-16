<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

$topTools = toolshare_admin_fetch_top_tools($pdo);
$monthlyBookings = toolshare_admin_chart_monthly_bookings($pdo);
$overview = toolshare_admin_overview($pdo);

$maxTopTools = 1;
foreach ($topTools as $row) {
    $maxTopTools = max($maxTopTools, (int)$row['rentals']);
}

$maxMonthly = 1;
foreach ($monthlyBookings as $row) {
    $maxMonthly = max($maxMonthly, (int)$row['total']);
}

toolshare_admin_render_layout_start($pdo, 'Reports', 'reports', 'Platform activity, top tools, and demand snapshots.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Marketplace Reports</h2>
    </div>
    <div class="admin-grid cols-2">
        <div class="admin-card" style="padding:18px;">
            <div class="admin-card-header"><h2 style="font-size:1rem;">Top Rented Tools</h2></div>
            <div class="admin-chart-list">
                <?php foreach ($topTools as $row): ?>
                    <?php $width = ((int)$row['rentals'] / $maxTopTools) * 100; ?>
                    <div class="admin-chart-row">
                        <strong><?= htmlspecialchars($row['title']) ?></strong>
                        <div class="admin-chart-track"><div class="admin-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                        <span><?= (int)$row['rentals'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="admin-card" style="padding:18px;">
            <div class="admin-card-header"><h2 style="font-size:1rem;">Bookings per Month</h2></div>
            <div class="admin-chart-list">
                <?php foreach ($monthlyBookings as $row): ?>
                    <?php $width = ((int)$row['total'] / $maxMonthly) * 100; ?>
                    <div class="admin-chart-row">
                        <strong><?= htmlspecialchars((string)$row['label']) ?></strong>
                        <div class="admin-chart-track"><div class="admin-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                        <span><?= (int)$row['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Snapshot</h2>
    </div>
    <div class="admin-grid cols-4">
        <div class="admin-stat"><h3><?= number_format($overview['total_bookings']) ?></h3><p>Total Bookings</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['active_rentals']) ?></h3><p>Active Rentals</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['disputes_raised']) ?></h3><p>Disputes Raised</p></div>
        <div class="admin-stat"><h3>$<?= number_format($overview['platform_earnings'], 2) ?></h3><p>Platform Earnings</p></div>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
