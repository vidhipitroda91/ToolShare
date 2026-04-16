<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

$rows = toolshare_admin_fetch_earnings($pdo);

toolshare_admin_render_layout_start($pdo, 'Earnings', 'earnings', 'Commission tracking and booking-level platform earnings.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Commission Tracking</h2>
    </div>
    <div class="admin-table-wrap">
        <table>
            <thead>
                <tr><th>Booking</th><th>Tool</th><th>Status</th><th>Rental Amount</th><th>Renter Commission</th><th>Owner Commission</th><th>Platform Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['booking_id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['tool_title']) ?></strong></td>
                        <td><span class="admin-badge-pill"><?= htmlspecialchars(ucfirst((string)$row['status'])) ?></span></td>
                        <td>$<?= number_format((float)$row['total_price'], 2) ?></td>
                        <td>$<?= number_format((float)$row['renter_commission'], 2) ?></td>
                        <td>$<?= number_format((float)$row['owner_commission'], 2) ?></td>
                        <td>$<?= number_format((float)$row['platform_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
