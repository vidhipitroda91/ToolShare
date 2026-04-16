<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

$rows = toolshare_admin_fetch_payouts($pdo);

toolshare_admin_render_layout_start($pdo, 'Payouts', 'payouts', 'Monitor owner settlements, payout holds, and released amounts.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Payout Queue</h2>
    </div>
    <div class="admin-table-wrap">
        <table>
            <thead>
                <tr><th>Booking</th><th>Tool</th><th>Owner</th><th>Rental Amount</th><th>Owner Commission</th><th>Owner Payout</th><th>Payout Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['booking_id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['tool_title']) ?></strong></td>
                        <td><?= htmlspecialchars($row['owner_name']) ?></td>
                        <td>$<?= number_format((float)$row['total_price'], 2) ?></td>
                        <td>$<?= number_format((float)$row['owner_commission'], 2) ?></td>
                        <td>$<?= number_format((float)$row['owner_payout'], 2) ?></td>
                        <td><span class="admin-badge-pill <?= $row['payout_status'] === 'released' ? 'success' : 'warning' ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$row['payout_status']))) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
