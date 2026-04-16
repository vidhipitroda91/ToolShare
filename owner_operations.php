<?php
session_start();
require 'config/db.php';
require 'includes/owner_layout.php';

$needs = toolshare_owner_needs_attention($pdo);
$opBookings = toolshare_admin_fetch_bookings($pdo, []);
$notifications = toolshare_admin_notifications($pdo, 10);

toolshare_owner_render_layout_start($pdo, 'Operational Pulse', 'operations', 'Review the operational load, service bottlenecks, and issues that could slow down the business.');
?>

<section class="owner-card">
    <div class="owner-card-header"><h2>Operational Workload</h2></div>
    <div class="owner-grid cols-4">
        <div class="owner-stat"><h3><?= number_format($needs['pending_disputes']) ?></h3><p>Pending Disputes</p></div>
        <div class="owner-stat"><h3><?= number_format($needs['return_reviews']) ?></h3><p>Return Reviews</p></div>
        <div class="owner-stat"><h3><?= number_format($needs['payouts_on_hold']) ?></h3><p>Payouts On Hold</p></div>
        <div class="owner-stat"><h3><?= number_format($needs['inactive_tools']) ?></h3><p>Inactive Tools</p></div>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header">
        <h2>Action Feed</h2>
        <?php if (toolshare_current_role() === 'admin'): ?>
            <a href="admin_dashboard.php" class="owner-btn">Open Operations Center</a>
        <?php endif; ?>
    </div>
    <?php if (empty($notifications)): ?>
        <div class="owner-note">Operations are clear right now.</div>
    <?php else: ?>
        <div class="owner-grid">
            <?php foreach ($notifications as $item): ?>
                <div class="owner-note">
                    <strong><?= htmlspecialchars($item['label']) ?></strong><br>
                    <span><?= htmlspecialchars($item['meta']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Latest Booking Operations View</h2></div>
    <div class="owner-table-wrap">
        <table>
            <thead><tr><th>Booking</th><th>Tool</th><th>Payment</th><th>Return</th><th>Dispute</th><th>Settlement</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($opBookings, 0, 12) as $booking): ?>
                    <?php
                        $returnStatus = 'In rental';
                        if ($booking['status'] === 'completed') {
                            $returnStatus = 'Completed';
                        } elseif (!empty($booking['returned_at']) && empty($booking['return_reviewed_at'])) {
                            $returnStatus = 'Awaiting owner review';
                        } elseif ($booking['dispute_status'] !== 'none' && !in_array($booking['dispute_status'], ['resolved', 'rejected'], true)) {
                            $returnStatus = 'Under dispute';
                        }
                    ?>
                    <tr>
                        <td>#<?= (int)$booking['id'] ?></td>
                        <td><strong><?= htmlspecialchars($booking['tool_title']) ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst((string)$booking['status'])) ?></td>
                        <td><?= htmlspecialchars($returnStatus) ?></td>
                        <td><?= htmlspecialchars(ucfirst((string)$booking['dispute_status'])) ?></td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$booking['settlement_status']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php toolshare_owner_render_layout_end(); ?>
