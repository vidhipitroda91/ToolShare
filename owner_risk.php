<?php
session_start();
require 'config/db.php';
require 'includes/owner_layout.php';

$overview = toolshare_owner_overview($pdo);
$risk = toolshare_owner_risk_breakdown($pdo);
$disputes = toolshare_admin_fetch_disputes($pdo, ['status' => 'pending']);
if (empty($disputes)) {
    $disputes = toolshare_admin_fetch_disputes($pdo, ['status' => 'reviewing']);
}

toolshare_owner_render_layout_start($pdo, 'Risk & Trust', 'risk', 'Track disputes, deposit exposure, return friction, and service issues that can damage the business.');
?>

<section class="owner-card">
    <div class="owner-card-header"><h2>Risk Summary</h2></div>
    <div class="owner-grid cols-4">
        <div class="owner-stat"><h3><?= number_format($overview['dispute_rate'], 1) ?>%</h3><p>Open Dispute Rate</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['cancellation_rate'], 1) ?>%</h3><p>Cancellation Rate</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['pending_returns']) ?></h3><p>Returns Waiting Review</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['open_disputes']) ?></h3><p>Open Disputes</p></div>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Exposure Breakdown</h2></div>
    <div class="owner-grid cols-4">
        <?php foreach ($risk as $item): ?>
            <div class="owner-stat">
                <h3><?= is_float($item['value']) ? '$' . number_format($item['value'], 2) : number_format((int)$item['value']) ?></h3>
                <p><?= htmlspecialchars($item['label']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Current High-Risk Cases</h2></div>
    <?php if (empty($disputes)): ?>
        <div class="owner-note">There are no active dispute cases right now.</div>
    <?php else: ?>
        <div class="owner-table-wrap">
            <table>
                <thead><tr><th>Dispute</th><th>Tool</th><th>Owner</th><th>Renter</th><th>Deposit</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($disputes, 0, 10) as $dispute): ?>
                        <tr>
                            <td>#<?= (int)$dispute['id'] ?> - <?= htmlspecialchars($dispute['reason']) ?></td>
                            <td><strong><?= htmlspecialchars($dispute['tool_title']) ?></strong></td>
                            <td><?= htmlspecialchars($dispute['owner_name']) ?></td>
                            <td><?= htmlspecialchars($dispute['renter_name']) ?></td>
                            <td>$<?= number_format((float)$dispute['deposit_held'], 2) ?></td>
                            <td><span class="owner-badge warning"><?= htmlspecialchars(ucfirst((string)$dispute['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php toolshare_owner_render_layout_end(); ?>
