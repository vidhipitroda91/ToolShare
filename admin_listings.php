<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

$search = trim($_GET['search'] ?? '');
$listings = toolshare_admin_fetch_listings($pdo, $search);

toolshare_admin_render_layout_start($pdo, 'Listings', 'listings', 'Review tool listings, owners, pricing, and marketplace availability.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Tool Listings</h2>
    </div>
    <form method="GET" class="admin-filter-row cols-2">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by tool title, category, or owner">
        <button type="submit" class="admin-btn">Search Listings</button>
    </form>
    <div class="admin-table-wrap">
        <table>
            <thead>
                <tr><th>Tool</th><th>Owner</th><th>Category</th><th>Hourly</th><th>Daily</th><th>Weekly</th><th>Deposit</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $listing): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($listing['title']) ?></strong></td>
                        <td><?= htmlspecialchars($listing['owner_name']) ?></td>
                        <td><?= htmlspecialchars((string)$listing['category']) ?></td>
                        <td>$<?= number_format((float)$listing['price_hourly'], 2) ?></td>
                        <td>$<?= number_format((float)$listing['price_daily'], 2) ?></td>
                        <td>$<?= number_format((float)$listing['price_weekly'], 2) ?></td>
                        <td>$<?= number_format((float)$listing['security_deposit'], 2) ?></td>
                        <td><span class="admin-badge-pill success">Active</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
