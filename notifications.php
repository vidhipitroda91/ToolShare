<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/notification_helpers.php';
require 'includes/site_chrome.php';

toolshare_require_user();

$userId = (int)($_SESSION['user_id'] ?? 0);
toolshare_mark_user_request_notifications_viewed($pdo, $userId);
$notificationCenter = toolshare_fetch_user_notification_center($pdo, $userId, 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
</head>
<body>
<?php toolshare_render_nav(); ?>
<main style="width:min(1100px, calc(100% - 32px)); margin:0 auto 40px; display:grid; gap:22px;">
    <section style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:28px; box-shadow:0 16px 34px rgba(15,23,42,0.05);">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h1 style="color:#15324a; font-size:2rem; margin-bottom:8px;">Notifications</h1>
                <p style="color:#64748b; line-height:1.7; max-width:720px;">New booking requests for your listed tools appear here, along with unread chat and support replies.</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <div style="padding:14px 16px; border-radius:18px; background:#f8fafc; border:1px solid #dbe4ec;">
                    <strong style="display:block; color:#15324a; font-size:1.15rem;"><?= number_format((int)$notificationCenter['unread_count']) ?></strong>
                    <span style="color:#64748b; font-size:0.86rem;">Unread alerts</span>
                </div>
                <div style="padding:14px 16px; border-radius:18px; background:#f8fafc; border:1px solid #dbe4ec;">
                    <strong style="display:block; color:#15324a; font-size:1.15rem;"><?= number_format((int)$notificationCenter['message_unread_count']) ?></strong>
                    <span style="color:#64748b; font-size:0.86rem;">Unread message threads</span>
                </div>
            </div>
        </div>
    </section>

    <section style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:24px; box-shadow:0 16px 34px rgba(15,23,42,0.05);">
        <?php if (empty($notificationCenter['items'])): ?>
            <div style="padding:18px; border-radius:18px; background:#f8fafc; border:1px dashed #cbd5e1; color:#64748b;">No unread notifications right now.</div>
        <?php else: ?>
            <div style="display:grid; gap:14px;">
                <?php foreach ($notificationCenter['items'] as $item): ?>
                    <a href="<?= htmlspecialchars((string)$item['href']) ?>" style="display:block; text-decoration:none; color:inherit; padding:18px; border-radius:20px; border:1px solid #dbe4ec; background:#fff;">
                        <div style="display:flex; justify-content:space-between; gap:14px; align-items:flex-start; margin-bottom:8px;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <strong style="color:#15324a; font-size:1rem;"><?= htmlspecialchars((string)$item['label']) ?></strong>
                                <?php if (!empty($item['is_unread'])): ?>
                                    <span style="display:inline-flex; width:10px; height:10px; border-radius:999px; background:#ef4444;"></span>
                                <?php endif; ?>
                            </div>
                            <span style="color:#64748b; font-size:0.85rem; white-space:nowrap;"><?= date('M d, Y h:i A', strtotime((string)$item['created_at'])) ?></span>
                        </div>
                        <p style="color:#64748b; line-height:1.65;"><?= htmlspecialchars((string)$item['meta']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
