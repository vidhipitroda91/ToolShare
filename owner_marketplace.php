<?php
session_start();
require 'config/db.php';
require 'includes/owner_layout.php';

$overview = toolshare_owner_overview($pdo);
$topCategories = toolshare_owner_top_categories($pdo);
$lowTools = toolshare_owner_low_performing_tools($pdo);
$users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$listings = (int)$pdo->query("SELECT COUNT(*) FROM tools")->fetchColumn();
$owners = (int)$pdo->query("SELECT COUNT(DISTINCT owner_id) FROM tools")->fetchColumn();

toolshare_owner_render_layout_start($pdo, 'Marketplace Health', 'marketplace', 'See what parts of the marketplace are growing, what inventory is healthy, and where demand is weak.');
?>

<section class="owner-card">
    <div class="owner-card-header"><h2>Marketplace Scale</h2></div>
    <div class="owner-grid cols-4">
        <div class="owner-stat"><h3><?= number_format($users) ?></h3><p>Customer Accounts</p></div>
        <div class="owner-stat"><h3><?= number_format($owners) ?></h3><p>Active Tool Owners</p></div>
        <div class="owner-stat"><h3><?= number_format($listings) ?></h3><p>Total Listings</p></div>
        <div class="owner-stat"><h3><?= number_format($overview['utilization_rate'], 1) ?>%</h3><p>Listings Utilized (30 Days)</p></div>
    </div>
</section>

<section class="owner-card">
    <div class="owner-card-header"><h2>Category Performance</h2></div>
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

<section class="owner-card">
    <div class="owner-card-header"><h2>Inventory Requiring Business Action</h2></div>
    <?php if (empty($lowTools)): ?>
        <div class="owner-note">All tools have recorded activity in the last 60 days.</div>
    <?php else: ?>
        <div class="owner-table-wrap">
            <table>
                <thead><tr><th>Tool</th><th>Category</th><th>Owner</th><th>60-Day Bookings</th></tr></thead>
                <tbody>
                    <?php foreach ($lowTools as $tool): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($tool['title']) ?></strong></td>
                            <td><?= htmlspecialchars($tool['category']) ?></td>
                            <td><?= htmlspecialchars($tool['owner_name']) ?></td>
                            <td><?= (int)$tool['bookings_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php toolshare_owner_render_layout_end(); ?>
