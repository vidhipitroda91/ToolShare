<?php
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/notification_helpers.php';

if (!function_exists('toolshare_admin_nav_items')) {
    function toolshare_admin_nav_items(): array
    {
        return [
            'dashboard' => ['label' => 'Ops Home', 'href' => 'admin_dashboard.php'],
            'bookings' => ['label' => 'Booking Queue', 'href' => 'admin_bookings.php'],
            'users' => ['label' => 'Users', 'href' => 'admin_users.php'],
            'listings' => ['label' => 'Listings', 'href' => 'admin_listings.php'],
            'disputes' => ['label' => 'Disputes', 'href' => 'admin_disputes.php'],
            'tickets' => ['label' => 'Support Tickets', 'href' => 'admin_tickets.php'],
            'deposits' => ['label' => 'Deposits', 'href' => 'admin_deposits.php'],
            'payouts' => ['label' => 'Payouts', 'href' => 'admin_payouts.php'],
            'earnings' => ['label' => 'Ops Earnings', 'href' => 'admin_earnings.php'],
            'notifications' => ['label' => 'Notifications', 'href' => 'admin_notifications.php'],
            'reports' => ['label' => 'Reports', 'href' => 'admin_reports.php'],
        ];
    }

    function toolshare_admin_render_layout_start(PDO $pdo, string $title, string $activePage, string $subtitle = ''): void
    {
        toolshare_require_admin();
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $notificationCenter = toolshare_fetch_admin_notification_center($pdo, $adminId, 8);
        $notifications = $notificationCenter['items'];
        $notificationCount = (int)$notificationCenter['unread_count'];
        $activeDisputeCount = (int)$notificationCenter['active_disputes'];
        $openTicketCount = (int)$notificationCenter['open_tickets'];
        $adminName = trim((string)($_SESSION['user_name'] ?? 'Admin'));
        $initial = strtoupper(substr($adminName, 0, 1)) ?: 'A';
        $role = toolshare_current_role();
        $roleLabel = $role === 'admin' ? 'Super Admin' : 'Operations';
        $navItems = toolshare_admin_nav_items();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?> | ToolShare Operations</title>
            <style>
                :root {
                    --admin-navy: #14324a;
                    --admin-navy-deep: #0e2234;
                    --admin-teal: #1f7a86;
                    --admin-cyan: #56b7c2;
                    --admin-bg: #f4f8fb;
                    --admin-card: #ffffff;
                    --admin-line: #dbe6ee;
                    --admin-text: #1e293b;
                    --admin-muted: #64748b;
                    --admin-danger: #dc2626;
                    --admin-warning: #d97706;
                    --admin-success: #059669;
                }
                * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
                body { background: linear-gradient(180deg, #edf4f7 0%, #f8fafc 100%); color: var(--admin-text); }
                .admin-shell { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
                .admin-sidebar {
                    background: linear-gradient(180deg, var(--admin-navy-deep) 0%, var(--admin-navy) 100%);
                    color: white;
                    padding: 28px 20px;
                    position: sticky;
                    top: 0;
                    height: 100vh;
                }
                .admin-brand { display: block; color: white; text-decoration: none; font-size: 1.5rem; font-weight: 900; letter-spacing: -0.05em; margin-bottom: 28px; }
                .admin-brand small { display: block; font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.7); letter-spacing: 0.04em; margin-top: 6px; }
                .admin-nav { display: grid; gap: 8px; }
                .admin-nav a {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 12px 14px;
                    border-radius: 14px;
                    color: rgba(255,255,255,0.82);
                    text-decoration: none;
                    font-weight: 700;
                    transition: 0.2s;
                }
                .admin-nav a:hover,
                .admin-nav a.active { background: rgba(86,183,194,0.16); color: white; }
                .admin-sidebar-footer { margin-top: 24px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.12); }
                .admin-sidebar-footer a { display: block; color: rgba(255,255,255,0.82); text-decoration: none; margin-bottom: 10px; }
                .admin-main { padding: 24px 28px 38px; }
                .admin-topbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 18px;
                    margin-bottom: 26px;
                }
                .admin-page-title h1 { color: var(--admin-navy); font-size: 2rem; margin-bottom: 4px; }
                .admin-page-title p { color: var(--admin-muted); }
                .admin-top-actions { display: flex; align-items: center; gap: 14px; }
                .admin-bell { position: relative; }
                .admin-bell summary {
                    list-style: none;
                    width: 48px;
                    height: 48px;
                    border-radius: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    background: #fff;
                    border: 1px solid var(--admin-line);
                    box-shadow: 0 8px 20px rgba(15,23,42,0.05);
                }
                .admin-bell summary::-webkit-details-marker { display: none; }
                .admin-badge {
                    position: absolute;
                    top: -6px;
                    right: -6px;
                    min-width: 22px;
                    height: 22px;
                    padding: 0 6px;
                    border-radius: 999px;
                    background: var(--admin-danger);
                    color: white;
                    font-size: 11px;
                    font-weight: 800;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }
                .admin-dropdown {
                    position: absolute;
                    top: calc(100% + 10px);
                    right: 0;
                    width: 360px;
                    background: white;
                    border: 1px solid var(--admin-line);
                    border-radius: 18px;
                    box-shadow: 0 24px 42px rgba(15,23,42,0.12);
                    padding: 12px;
                    z-index: 30;
                }
                .admin-dropdown h4 { color: var(--admin-navy); margin-bottom: 10px; padding: 6px 8px; }
                .admin-dropdown a { display: block; padding: 10px 12px; border-radius: 12px; text-decoration: none; color: var(--admin-text); }
                .admin-dropdown a:hover { background: #f8fafc; }
                .admin-dropdown small { display: block; color: var(--admin-muted); margin-top: 4px; line-height: 1.5; }
                .admin-profile {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 9px 14px;
                    border-radius: 14px;
                    background: #fff;
                    border: 1px solid var(--admin-line);
                    box-shadow: 0 8px 20px rgba(15,23,42,0.05);
                }
                .admin-avatar {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(31,122,134,0.12);
                    color: var(--admin-teal);
                    font-weight: 800;
                }
                .admin-content { display: grid; gap: 22px; }
                .admin-card {
                    background: rgba(255,255,255,0.92);
                    border: 1px solid rgba(255,255,255,0.86);
                    border-radius: 24px;
                    padding: 24px;
                    box-shadow: 0 16px 34px rgba(15,23,42,0.05);
                }
                .admin-card-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; }
                .admin-card-header h2 { color: var(--admin-navy); font-size: 1.25rem; }
                .admin-grid { display: grid; gap: 18px; }
                .admin-grid.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .admin-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                .admin-grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
                .admin-stat {
                    background: #fff;
                    border: 1px solid var(--admin-line);
                    border-radius: 18px;
                    padding: 18px;
                }
                .admin-stat h3 { color: var(--admin-navy); font-size: 1.8rem; margin-bottom: 6px; }
                .admin-stat p { color: var(--admin-muted); text-transform: uppercase; letter-spacing: 0.06em; font-size: 0.78rem; font-weight: 800; }
                .admin-table-wrap { overflow-x: auto; }
                table { width: 100%; border-collapse: collapse; }
                th { text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: var(--admin-muted); border-bottom: 1px solid var(--admin-line); }
                td { padding: 14px 12px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
                .admin-filter-row { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; }
                .admin-filter-row.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                input, select, textarea {
                    width: 100%;
                    padding: 11px 12px;
                    border: 1px solid var(--admin-line);
                    border-radius: 12px;
                    font-size: 14px;
                    outline: none;
                    background: white;
                }
                textarea { min-height: 120px; resize: vertical; }
                input:focus, select:focus, textarea:focus { border-color: var(--admin-teal); box-shadow: 0 0 0 4px rgba(31,122,134,0.08); }
                .admin-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 42px;
                    padding: 0 16px;
                    border: none;
                    border-radius: 999px;
                    text-decoration: none;
                    cursor: pointer;
                    background: var(--admin-teal);
                    color: white;
                    font-weight: 800;
                }
                .admin-btn.secondary { background: #e2e8f0; color: var(--admin-navy); }
                .admin-btn.danger { background: var(--admin-danger); }
                .admin-badge-pill {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 5px 10px;
                    border-radius: 999px;
                    font-size: 12px;
                    font-weight: 800;
                    background: #e7f4f5;
                    color: var(--admin-teal);
                }
                .admin-badge-pill.warning { background: #fff7ed; color: var(--admin-warning); }
                .admin-badge-pill.success { background: #ecfdf5; color: var(--admin-success); }
                .admin-badge-pill.danger { background: #fef2f2; color: var(--admin-danger); }
                .admin-chart-list { display: grid; gap: 12px; }
                .admin-chart-row { display: grid; grid-template-columns: 140px 1fr 70px; gap: 12px; align-items: center; }
                .admin-chart-track { background: #e2e8f0; border-radius: 999px; height: 12px; overflow: hidden; }
                .admin-chart-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--admin-teal), var(--admin-cyan)); }
                .admin-note { padding: 14px; border-radius: 16px; background: #f8fafc; border: 1px dashed #cbd5e1; color: var(--admin-muted); }
                .admin-thumbnail-list { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
                .admin-thumbnail-list img { width: 84px; height: 84px; object-fit: cover; border-radius: 12px; border: 1px solid var(--admin-line); }
                .admin-alert {
                    padding: 12px 14px;
                    border-radius: 14px;
                    background: #ecfeff;
                    color: var(--admin-navy);
                    border: 1px solid #bae6fd;
                }
                @media (max-width: 1100px) {
                    .admin-shell { grid-template-columns: 1fr; }
                    .admin-sidebar { position: static; height: auto; }
                    .admin-grid.cols-2, .admin-grid.cols-3, .admin-grid.cols-4, .admin-filter-row, .admin-filter-row.cols-2 { grid-template-columns: 1fr; }
                }
            </style>
        </head>
        <body>
        <div class="admin-shell">
            <aside class="admin-sidebar">
                <a href="admin_dashboard.php" class="admin-brand">
                    ToolShare
                    <small>OPERATIONS CENTER</small>
                </a>
                <nav class="admin-nav">
                    <?php foreach ($navItems as $key => $item): ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $activePage === $key ? 'active' : '' ?>">
                            <span><?= htmlspecialchars($item['label']) ?></span>
                            <?php if ($key === 'notifications' && $notificationCount > 0): ?>
                                <span class="admin-badge-pill danger"><?= $notificationCount ?></span>
                            <?php elseif ($key === 'disputes' && $activeDisputeCount > 0): ?>
                                <span class="admin-badge-pill danger"><?= $activeDisputeCount ?></span>
                            <?php elseif ($key === 'tickets' && $openTicketCount > 0): ?>
                                <span class="admin-badge-pill danger"><?= $openTicketCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="admin-sidebar-footer">
                    <?php if ($role === 'admin'): ?>
                        <a href="owner_dashboard.php">Owner Software</a>
                    <?php endif; ?>
                    <a href="profile.php">View Profile</a>
                    <a href="logout.php">Logout</a>
                </div>
            </aside>
            <main class="admin-main">
                <div class="admin-topbar">
                    <div class="admin-page-title">
                        <h1><?= htmlspecialchars($title) ?></h1>
                        <p><?= htmlspecialchars($subtitle) ?></p>
                    </div>
                    <div class="admin-top-actions">
                        <details class="admin-bell">
                            <summary>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#14324a" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"></path><path d="M10 21a2 2 0 0 0 4 0"></path></svg>
                                <?php if ($notificationCount > 0): ?><span class="admin-badge"><?= $notificationCount ?></span><?php endif; ?>
                            </summary>
                            <div class="admin-dropdown">
                                <h4>Notifications</h4>
                                <?php if (empty($notifications)): ?>
                                    <div class="admin-note">No active admin notifications right now.</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $item): ?>
                                        <a href="<?= htmlspecialchars($item['href']) ?>">
                                            <strong><?= htmlspecialchars($item['label']) ?></strong>
                                            <small><?= htmlspecialchars($item['meta']) ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                    <a href="admin_notifications.php" class="admin-btn secondary" style="margin: 8px 8px 4px;">View all notifications</a>
                                <?php endif; ?>
                            </div>
                        </details>
                        <div class="admin-profile">
                            <span class="admin-avatar"><?= htmlspecialchars($initial) ?></span>
                            <div>
                                <strong><?= htmlspecialchars($adminName) ?></strong><br>
                                <small style="color: var(--admin-muted);"><?= htmlspecialchars($roleLabel) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($_GET['msg'])): ?>
                    <div class="admin-alert" style="margin-bottom: 18px;"><?= htmlspecialchars((string)$_GET['msg']) ?></div>
                <?php endif; ?>
                <div class="admin-content">
        <?php
    }

    function toolshare_admin_render_layout_end(): void
    {
        ?>
                </div>
            </main>
        </div>
        <script>
            document.addEventListener('click', function (event) {
                document.querySelectorAll('.admin-bell[open]').forEach(function (dropdown) {
                    if (!dropdown.contains(event.target)) {
                        dropdown.removeAttribute('open');
                    }
                });
            });
        </script>
        </body>
        </html>
        <?php
    }
}
