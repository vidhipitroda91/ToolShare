<?php
session_start();
require 'config/db.php';
require 'includes/owner_layout.php';

$overview = toolshare_owner_overview($pdo);
$attention = toolshare_owner_needs_attention($pdo);
$revenueRows = toolshare_admin_chart_monthly_revenue($pdo);
$monthlyBookings = toolshare_admin_chart_monthly_bookings($pdo);
$topCategories = toolshare_owner_top_categories($pdo);

$maxRevenue = 1.0;
foreach ($revenueRows as $row) {
    $maxRevenue = max($maxRevenue, (float)$row['revenue']);
}
$maxBookings = 1;
foreach ($monthlyBookings as $row) {
    $maxBookings = max($maxBookings, (int)$row['total']);
}

toolshare_owner_render_layout_start($pdo, 'Executive Overview', 'dashboard', 'High-level performance, growth direction, and issues requiring leadership attention.');
?>

<section class="owner-card">
    <div class="owner-card-header"><h2>Core Business Snapshot</h2></div>
    <div class="owner-grid cols-4">
        <div class="owner-stat"><h3>$<?= number_format($overview['gross_revenue'], 2) ?></h3><p>Gross Revenue</p></div>
        <div class="owner-stat"><h3>$<?= number_format($overview['platform_earnings'], 2) ?></h3><p>Platform Earnings</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['active_rentals']) ?></h3><p>Active Right Now</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['upcoming_rentals']) ?></h3><p>Upcoming Paid Rentals</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['overdue_returns']) ?></h3><p>Overdue Returns</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['utilization_rate'], 1) ?>%</h3><p>30-Day Utilization</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['repeat_renters']) ?></h3><p>Repeat Renters</p></div>
        <div class="owner-stat"><h3>$<?= number_format($overview['avg_booking_value'], 2) ?></h3><p>Average Booking Value</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['cancellation_rate'], 1) ?>%</h3><p>Cancellation Rate</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['dispute_rate'], 1) ?>%</h3><p>Dispute Rate</p></div>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header">
        <h2>Needs Leadership Attention</h2>
        <a href="owner_operations.php" class="owner-btn">Open Operational Pulse</a>
    </div>
    <div class="owner-grid cols-4">
        <div class="owner-stat"><h3><?= number_format($attention['pending_disputes']) ?></h3><p>Pending Disputes</p></div>
        <div class="owner-stat"><h3><?= number_format($attention['return_reviews']) ?></h3><p>Return Reviews Waiting</p></div>
        <div class="owner-stat"><h3><?= number_format($attention['payouts_on_hold']) ?></h3><p>Payouts On Hold</p></div>
        <div class="owner-stat"><h3><?= number_format($attention['overdue_returns']) ?></h3><p>Overdue Returns</p></div>
        <div class="owner-stat"><h3><?= number_format($attention['inactive_tools']) ?></h3><p>Inactive Tools</p></div>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Growth Trends</h2></div>
    <div class="owner-grid cols-2">
        <div class="owner-card" style="padding:18px;">
            <div class="owner-card-header"><h2 style="font-size:1rem;">Revenue Trend</h2></div>
            <div class="owner-chart-list">
                <?php if (empty($revenueRows)): ?>
                    <div class="owner-note">No revenue data available yet.</div>
                <?php else: ?>
                    <?php foreach ($revenueRows as $row): ?>
                        <?php $width = ((float)$row['revenue'] / $maxRevenue) * 100; ?>
                        <div class="owner-chart-row">
                            <strong><?= htmlspecialchars((string)$row['label']) ?></strong>
                            <div class="owner-chart-track"><div class="owner-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                            <span>$<?= number_format((float)$row['revenue'], 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="owner-card" style="padding:18px;">
            <div class="owner-card-header"><h2 style="font-size:1rem;">Booking Volume</h2></div>
            <div class="owner-chart-list">
                <?php if (empty($monthlyBookings)): ?>
                    <div class="owner-note">No booking history available yet.</div>
                <?php else: ?>
                    <?php foreach ($monthlyBookings as $row): ?>
                        <?php $width = ((int)$row['total'] / $maxBookings) * 100; ?>
                        <div class="owner-chart-row">
                            <strong><?= htmlspecialchars((string)$row['label']) ?></strong>
                            <div class="owner-chart-track"><div class="owner-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                            <span><?= (int)$row['total'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Top Revenue Categories</h2></div>
    <div class="owner-table-wrap">
        <table>
            <thead><tr><th>Category</th><th>Bookings</th><th>Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($topCategories as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string)$row['category']) ?></strong></td>
                        <td><?= (int)$row['bookings_count'] ?></td>
                        <td>$<?= number_format((float)$row['revenue'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php toolshare_owner_render_layout_end(); ?>
