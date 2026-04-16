<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

$rows = toolshare_admin_fetch_deposits($pdo);

toolshare_admin_render_layout_start($pdo, 'Deposits', 'deposits', 'Track held deposits, outcomes, and dispute deductions.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Deposit Ledger</h2>
    </div>
    <div class="admin-table-wrap">
        <table>
            <thead>
                <tr><th>Booking</th><th>Tool</th><th>Owner</th><th>Renter</th><th>Deposit</th><th>Booking Status</th><th>Outcome</th><th>Refunded</th><th>Deducted</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['booking_id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['tool_title']) ?></strong></td>
                        <td><?= htmlspecialchars($row['owner_name']) ?></td>
                        <td><?= htmlspecialchars($row['renter_name']) ?></td>
                        <td>$<?= number_format((float)$row['security_deposit'], 2) ?></td>
                        <td><span class="admin-badge-pill"><?= htmlspecialchars(ucfirst((string)$row['booking_status'])) ?></span></td>
                        <td><span class="admin-badge-pill <?= $row['deposit_outcome'] === 'held' ? 'warning' : 'success' ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$row['deposit_outcome']))) ?></span></td>
                        <td>$<?= number_format((float)$row['deposit_refund_amount'], 2) ?></td>
                        <td>$<?= number_format((float)$row['deducted_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
