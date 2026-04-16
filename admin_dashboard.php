<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

$overview = toolshare_admin_overview($pdo);
$bookingStatus = toolshare_admin_chart_booking_status($pdo);
$disputeStatus = toolshare_admin_chart_dispute_status($pdo);
$revenueRows = toolshare_admin_chart_monthly_revenue($pdo);
$monthlyBookings = toolshare_admin_chart_monthly_bookings($pdo);
$needsAction = toolshare_admin_needs_action($pdo);

$maxBookingStatus = 1;
foreach ($bookingStatus as $row) {
    $maxBookingStatus = max($maxBookingStatus, (int)$row['total']);
}

$maxRevenue = 1.0;
foreach ($revenueRows as $row) {
    $maxRevenue = max($maxRevenue, (float)$row['revenue']);
}

$maxMonthlyBookings = 1;
foreach ($monthlyBookings as $row) {
    $maxMonthlyBookings = max($maxMonthlyBookings, (int)$row['total']);
}

toolshare_admin_render_layout_start($pdo, 'Dashboard', 'dashboard', 'Operations snapshot, action items, and platform analytics.');
?>

<section class="admin-card" id="overview">
    <div class="admin-card-header">
        <h2>Overview</h2>
    </div>
    <div class="admin-grid cols-4">
        <div class="admin-stat"><h3><?= number_format($overview['total_users']) ?></h3><p>Total Users</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['total_listings']) ?></h3><p>Total Listings</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['total_bookings']) ?></h3><p>Total Bookings</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['active_rentals']) ?></h3><p>Active Right Now</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['upcoming_rentals']) ?></h3><p>Upcoming Paid Rentals</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['overdue_returns']) ?></h3><p>Overdue Returns</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['completed_rentals']) ?></h3><p>Completed Rentals</p></div>
        <div class="admin-stat"><h3><?= number_format($overview['disputes_raised']) ?></h3><p>Disputes Raised</p></div>
        <div class="admin-stat"><h3>$<?= number_format($overview['deposits_held'], 2) ?></h3><p>Deposits Held</p></div>
        <div class="admin-stat"><h3>$<?= number_format($overview['deposits_refunded'], 2) ?></h3><p>Deposits Refunded</p></div>
        <div class="admin-stat"><h3>$<?= number_format($overview['deposits_deducted'], 2) ?></h3><p>Deposits Deducted</p></div>
        <div class="admin-stat"><h3>$<?= number_format($overview['pending_payouts'], 2) ?></h3><p>Pending Payouts</p></div>
        <div class="admin-stat"><h3>$<?= number_format($overview['released_payouts'], 2) ?></h3><p>Released Payouts</p></div>
        <div class="admin-stat"><h3>$<?= number_format($overview['platform_earnings'], 2) ?></h3><p>Platform Earnings</p></div>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Needs Action</h2>
        <a href="admin_notifications.php" class="admin-btn">Open Queue</a>
    </div>
    <div class="admin-grid cols-4">
        <div class="admin-stat"><h3><?= number_format($needsAction['pending_disputes']) ?></h3><p>Pending Disputes</p></div>
        <div class="admin-stat"><h3><?= number_format($needsAction['reviewing_disputes']) ?></h3><p>Disputes In Review</p></div>
        <div class="admin-stat"><h3><?= number_format($needsAction['awaiting_return']) ?></h3><p>Owner Review Queue</p></div>
        <div class="admin-stat"><h3><?= number_format($needsAction['overdue_returns']) ?></h3><p>Renter Overdue Returns</p></div>
        <div class="admin-stat"><h3><?= number_format($needsAction['pending_payouts']) ?></h3><p>Open Rental Pipeline</p></div>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Charts & Trends</h2>
    </div>
    <div class="admin-grid cols-2">
        <div class="admin-card" style="padding:18px;">
            <div class="admin-card-header"><h2 style="font-size:1rem;">Booking Status</h2></div>
            <div class="admin-chart-list">
                <?php foreach ($bookingStatus as $row): ?>
                    <?php $width = ((int)$row['total'] / $maxBookingStatus) * 100; ?>
                    <div class="admin-chart-row">
                        <strong><?= htmlspecialchars(ucfirst((string)$row['status'])) ?></strong>
                        <div class="admin-chart-track"><div class="admin-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                        <span><?= (int)$row['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="admin-card" style="padding:18px;">
            <div class="admin-card-header"><h2 style="font-size:1rem;">Dispute Trend</h2></div>
            <div class="admin-chart-list">
                <?php if (empty($disputeStatus)): ?>
                    <div class="admin-note">No disputes yet.</div>
                <?php else: ?>
                    <?php
                    $maxDisputes = 1;
                    foreach ($disputeStatus as $row) {
                        $maxDisputes = max($maxDisputes, (int)$row['total']);
                    }
                    ?>
                    <?php foreach ($disputeStatus as $row): ?>
                        <?php $width = ((int)$row['total'] / $maxDisputes) * 100; ?>
                        <div class="admin-chart-row">
                            <strong><?= htmlspecialchars(ucfirst((string)$row['status'])) ?></strong>
                            <div class="admin-chart-track"><div class="admin-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                            <span><?= (int)$row['total'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="admin-card" style="padding:18px;">
            <div class="admin-card-header"><h2 style="font-size:1rem;">Revenue / Earnings</h2></div>
            <div class="admin-chart-list">
                <?php if (empty($revenueRows)): ?>
                    <div class="admin-note">No revenue data available.</div>
                <?php else: ?>
                    <?php foreach ($revenueRows as $row): ?>
                        <?php $width = (((float)$row['revenue']) / $maxRevenue) * 100; ?>
                        <div class="admin-chart-row">
                            <strong><?= htmlspecialchars((string)$row['label']) ?></strong>
                            <div class="admin-chart-track"><div class="admin-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                            <span>$<?= number_format((float)$row['revenue'], 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="admin-card" style="padding:18px;">
            <div class="admin-card-header"><h2 style="font-size:1rem;">Monthly Activity</h2></div>
            <div class="admin-chart-list">
                <?php if (empty($monthlyBookings)): ?>
                    <div class="admin-note">No booking activity yet.</div>
                <?php else: ?>
                    <?php foreach ($monthlyBookings as $row): ?>
                        <?php $width = (((int)$row['total']) / $maxMonthlyBookings) * 100; ?>
                        <div class="admin-chart-row">
                            <strong><?= htmlspecialchars((string)$row['label']) ?></strong>
                            <div class="admin-chart-track"><div class="admin-chart-fill" style="width: <?= number_format($width, 2, '.', '') ?>%;"></div></div>
                            <span><?= (int)$row['total'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
