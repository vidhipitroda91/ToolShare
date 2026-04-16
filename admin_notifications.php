<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

$adminId = (int)($_SESSION['user_id'] ?? 0);
toolshare_mark_admin_notification_center_viewed($pdo, $adminId);
$notificationCenter = toolshare_fetch_admin_notification_center($pdo, $adminId, 40);
$notifications = $notificationCenter['items'];

toolshare_admin_render_layout_start($pdo, 'Notifications', 'notifications', 'All active admin alerts and review items.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Notification Center</h2>
    </div>
    <?php if (empty($notifications)): ?>
        <div class="admin-note">No open notifications right now.</div>
    <?php else: ?>
        <div class="admin-grid">
            <?php foreach ($notifications as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="admin-card" style="padding:18px; text-decoration:none; color:inherit;">
                    <div class="admin-card-header" style="margin-bottom:8px;">
                        <h2 style="font-size:1rem;"><?= htmlspecialchars($item['label']) ?></h2>
                        <span class="admin-badge-pill <?= $item['type'] === 'dispute' ? 'danger' : 'warning' ?>"><?= htmlspecialchars(ucfirst($item['type'])) ?></span>
                    </div>
                    <p style="color:#64748b; line-height:1.6;"><?= htmlspecialchars($item['meta']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php toolshare_admin_render_layout_end(); ?>
