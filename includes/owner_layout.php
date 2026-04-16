<?php
require_once __DIR__ . '/owner_helpers.php';

if (!function_exists('toolshare_owner_nav_items')) {
    function toolshare_owner_nav_items(): array
    {
        return [
            'dashboard' => ['label' => 'Overview', 'href' => 'owner_dashboard.php'],
            'revenue' => ['label' => 'Revenue', 'href' => 'owner_revenue.php'],
            'marketplace' => ['label' => 'Marketplace', 'href' => 'owner_marketplace.php'],
            'operations' => ['label' => 'Operations', 'href' => 'owner_operations.php'],
            'risk' => ['label' => 'Risk', 'href' => 'owner_risk.php'],
        ];
    }

    function toolshare_owner_render_layout_start(PDO $pdo, string $title, string $activePage, string $subtitle = ''): void
    {
        toolshare_require_owner_admin();
        $ownerName = trim((string)($_SESSION['user_name'] ?? 'Owner'));
        $initial = strtoupper(substr($ownerName, 0, 1)) ?: 'O';
        $navItems = toolshare_owner_nav_items();
        $attention = toolshare_owner_needs_attention($pdo);
        $attentionCount = array_sum($attention);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?> | ToolShare Owner</title>
            <style>
                :root {
                    --owner-navy: #10273f;
                    --owner-emerald: #0f766e;
                    --owner-mint: #d8f3ee;
                    --owner-gold: #b88a44;
                    --owner-bg: #f5f8fb;
                    --owner-card: #ffffff;
                    --owner-line: #dbe4ec;
                    --owner-text: #1e293b;
                    --owner-muted: #64748b;
                    --owner-danger: #c2410c;
                }
                * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
                body { background: linear-gradient(180deg, #eff5f8 0%, #f8fafc 100%); color: var(--owner-text); }
                .owner-shell { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
                .owner-sidebar {
                    background: linear-gradient(180deg, #0b1f32 0%, var(--owner-navy) 100%);
                    color: white;
                    padding: 28px 20px;
                    position: sticky;
                    top: 0;
                    height: 100vh;
                }
                .owner-brand { display: block; color: white; text-decoration: none; font-size: 1.55rem; font-weight: 900; letter-spacing: -0.05em; margin-bottom: 26px; }
                .owner-brand small { display: block; font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.7); letter-spacing: 0.05em; margin-top: 6px; }
                .owner-nav { display: grid; gap: 8px; }
                .owner-nav a {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 12px 14px;
                    border-radius: 14px;
                    color: rgba(255,255,255,0.84);
                    text-decoration: none;
                    font-weight: 700;
                    transition: 0.2s;
                }
                .owner-nav a:hover, .owner-nav a.active { background: rgba(15,118,110,0.18); color: white; }
                .owner-chip { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: #ef4444; color: white; font-size: 11px; font-weight: 800; }
                .owner-sidebar-footer { margin-top: 24px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.12); }
                .owner-sidebar-footer a { display: block; color: rgba(255,255,255,0.82); text-decoration: none; margin-bottom: 10px; }
                .owner-main { padding: 24px 28px 40px; }
                .owner-topbar { display: flex; justify-content: space-between; align-items: center; gap: 18px; margin-bottom: 26px; }
                .owner-title h1 { color: var(--owner-navy); font-size: 2rem; margin-bottom: 4px; }
                .owner-title p { color: var(--owner-muted); }
                .owner-profile { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 14px; background: #fff; border: 1px solid var(--owner-line); box-shadow: 0 10px 20px rgba(15,23,42,0.04); }
                .owner-avatar { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--owner-mint); color: var(--owner-emerald); font-weight: 800; }
                .owner-content { display: grid; gap: 22px; }
                .owner-card { background: rgba(255,255,255,0.94); border: 1px solid rgba(255,255,255,0.86); border-radius: 24px; padding: 24px; box-shadow: 0 16px 34px rgba(15,23,42,0.05); }
                .owner-card-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; }
                .owner-card-header h2 { color: var(--owner-navy); font-size: 1.2rem; }
                .owner-grid { display: grid; gap: 18px; }
                .owner-grid.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .owner-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                .owner-grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
                .owner-stat { background: #fff; border: 1px solid var(--owner-line); border-radius: 18px; padding: 18px; }
                .owner-stat h3 { color: var(--owner-navy); font-size: 1.8rem; margin-bottom: 6px; }
                .owner-stat p { color: var(--owner-muted); text-transform: uppercase; letter-spacing: 0.06em; font-size: 0.78rem; font-weight: 800; }
                .owner-note { padding: 14px; border-radius: 16px; background: #f8fafc; border: 1px dashed #cbd5e1; color: var(--owner-muted); }
                .owner-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border: none; border-radius: 999px; text-decoration: none; cursor: pointer; background: var(--owner-emerald); color: white; font-weight: 800; }
                .owner-chart-list { display: grid; gap: 12px; }
                .owner-chart-row { display: grid; grid-template-columns: 160px 1fr 80px; gap: 12px; align-items: center; }
                .owner-chart-track { background: #e2e8f0; border-radius: 999px; height: 12px; overflow: hidden; }
                .owner-chart-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--owner-emerald), #38b2ac); }
                .owner-table-wrap { overflow-x: auto; }
                table { width: 100%; border-collapse: collapse; }
                th { text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: var(--owner-muted); border-bottom: 1px solid var(--owner-line); }
                td { padding: 14px 12px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
                .owner-badge { display: inline-flex; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; background: #edfdf7; color: var(--owner-emerald); }
                .owner-badge.warning { background: #fff7ed; color: var(--owner-danger); }
                @media (max-width: 1100px) {
                    .owner-shell { grid-template-columns: 1fr; }
                    .owner-sidebar { position: static; height: auto; }
                    .owner-grid.cols-2, .owner-grid.cols-3, .owner-grid.cols-4 { grid-template-columns: 1fr; }
                }
            </style>
        </head>
        <body>
        <div class="owner-shell">
            <aside class="owner-sidebar">
                <a href="owner_dashboard.php" class="owner-brand">
                    ToolShare
                    <small>OWNER SOFTWARE</small>
                </a>
                <nav class="owner-nav">
                    <?php foreach ($navItems as $key => $item): ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $activePage === $key ? 'active' : '' ?>">
                            <span><?= htmlspecialchars($item['label']) ?></span>
                            <?php if ($key === 'operations' && $attentionCount > 0): ?>
                                <span class="owner-chip"><?= $attentionCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="owner-sidebar-footer">
                    <?php if (toolshare_current_role() === 'admin'): ?>
                        <a href="admin_dashboard.php">Operations Center</a>
                    <?php endif; ?>
                    <a href="profile.php">View Profile</a>
                    <a href="index.php">Back to Site</a>
                    <a href="logout.php">Logout</a>
                </div>
            </aside>
            <main class="owner-main">
                <div class="owner-topbar">
                    <div class="owner-title">
                        <h1><?= htmlspecialchars($title) ?></h1>
                        <p><?= htmlspecialchars($subtitle) ?></p>
                    </div>
                    <div class="owner-profile">
                        <span class="owner-avatar"><?= htmlspecialchars($initial) ?></span>
                        <div>
                            <strong><?= htmlspecialchars($ownerName) ?></strong><br>
                            <small style="color: var(--owner-muted);">Business Owner</small>
                        </div>
                    </div>
                </div>
                <div class="owner-content">
        <?php
    }

    function toolshare_owner_render_layout_end(): void
    {
        ?>
                </div>
            </main>
        </div>
        </body>
        </html>
        <?php
    }
}
