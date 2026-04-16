<?php
require_once __DIR__ . '/support_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/auth.php';
if (!function_exists('toolshare_render_chrome_assets')) {
    function toolshare_render_chrome_assets(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <style>
            :root {
                --ts-nav-height: 72px;
                --ts-navy: #15324a;
                --ts-navy-deep: #0f2233;
                --ts-teal: #1f6f78;
                --ts-teal-soft: #d8ecef;
                --ts-ink: #102338;
                --ts-surface: #f6fafb;
                --ts-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            }

            html {
                scroll-behavior: smooth;
            }

            body {
                padding-top: calc(var(--ts-nav-height) + 20px);
                transition: opacity 0.24s ease, transform 0.24s ease;
            }

            body.ts-auth-page {
                min-height: 100vh;
            }

            body.ts-page-leaving {
                opacity: 0;
                transform: translateY(10px);
            }

            .ts-nav-shell {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 5000;
                background: rgba(246, 250, 251, 0.96);
                border-bottom: 1px solid rgba(21, 50, 74, 0.08);
                box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8);
            }

            .ts-focus-shell {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 5000;
                background: rgba(246, 250, 251, 0.96);
                border-bottom: 1px solid rgba(21, 50, 74, 0.08);
                box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8);
            }

            .ts-focus-bar {
                width: min(1100px, calc(100% - 32px));
                margin: 0 auto;
                min-height: var(--ts-nav-height);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 12px 0;
            }

            .ts-focus-copy {
                display: flex;
                flex-direction: column;
                gap: 2px;
                min-width: 0;
            }

            .ts-focus-kicker {
                font-size: 11px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                font-weight: 800;
                color: #648097;
            }

            .ts-focus-title {
                font-size: 1rem;
                font-weight: 800;
                color: var(--ts-navy);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .ts-focus-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                padding: 0 16px;
                border-radius: 999px;
                border: 1px solid rgba(21, 50, 74, 0.12);
                background: #ffffff;
                color: var(--ts-navy);
                text-decoration: none;
                font-weight: 800;
                white-space: nowrap;
            }

            .ts-navbar {
                width: min(1320px, calc(100% - 32px));
                margin: 0 auto;
                min-height: var(--ts-nav-height);
                display: grid;
                grid-template-columns: auto minmax(240px, 460px) auto;
                align-items: center;
                gap: 18px;
                padding: 12px 0;
            }

            .ts-brand {
                color: var(--ts-navy);
                text-decoration: none;
                font-size: 1.35rem;
                font-weight: 900;
                letter-spacing: -0.06em;
            }

            .ts-nav-search {
                position: relative;
                width: 100%;
            }

            .ts-nav-search input {
                width: 100%;
                height: 44px;
                padding: 0 18px 0 50px;
                border-radius: 999px;
                border: 1px solid rgba(21, 50, 74, 0.12);
                background: #ffffff;
                color: var(--ts-navy);
                outline: none;
                font-size: 0.96rem;
            }

            .ts-nav-search input::placeholder {
                color: #7b8a97;
            }

            .ts-nav-search svg {
                position: absolute;
                left: 18px;
                top: 50%;
                transform: translateY(-50%);
                width: 18px;
                height: 18px;
                color: #67808d;
                pointer-events: none;
            }

            .ts-nav-links {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 12px;
                flex-wrap: wrap;
            }

            .ts-link,
            .ts-icon-link,
            .ts-dropdown summary {
                color: var(--ts-navy);
                text-decoration: none;
                font-weight: 700;
                font-size: 0.92rem;
                padding: 9px 12px;
                border-radius: 10px;
                list-style: none;
                cursor: pointer;
                transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
            }

            .ts-link:hover,
            .ts-icon-link:hover,
            .ts-dropdown summary:hover,
            .ts-dropdown[open] summary {
                background: rgba(31, 111, 120, 0.08);
                transform: translateY(-1px);
            }

            .ts-icon-link {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                border: none;
                background: transparent;
            }

            .ts-icon-link svg {
                width: 18px;
                height: 18px;
            }

            .ts-unread-dot {
                position: absolute;
                top: 5px;
                right: 5px;
                width: 10px;
                height: 10px;
                border-radius: 999px;
                background: #ef4444;
                box-shadow: 0 0 0 3px rgba(246, 250, 251, 0.96);
            }

            .ts-dropdown {
                position: relative;
            }

            .ts-dropdown summary::-webkit-details-marker {
                display: none;
            }

            .ts-summary-inline {
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }

            .ts-caret {
                width: 8px;
                height: 8px;
                border-right: 2px solid currentColor;
                border-bottom: 2px solid currentColor;
                transform: rotate(45deg) translateY(-1px);
                opacity: 0.8;
            }

            .ts-menu {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                min-width: 220px;
                padding: 10px;
                border-radius: 14px;
                border: 1px solid rgba(21, 50, 74, 0.12);
                background: #ffffff;
                box-shadow: var(--ts-shadow);
            }

            .ts-notification-menu {
                width: min(420px, calc(100vw - 32px));
                min-width: 360px;
                padding: 16px;
            }

            .ts-notification-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 14px;
            }

            .ts-notification-head strong {
                color: var(--ts-navy);
                font-size: 1.15rem;
                line-height: 1.2;
            }

            .ts-notification-count {
                color: #64748b;
                font-size: 0.82rem;
                font-weight: 700;
            }

            .ts-notification-list {
                display: grid;
                gap: 10px;
                max-height: min(420px, 62vh);
                overflow-y: auto;
                padding-right: 2px;
            }

            .ts-notification-entry {
                display: block;
                padding: 14px 15px;
                border-radius: 18px;
                border: 1px solid #dbe4ec;
                background: #f8fbfc;
                text-decoration: none;
                color: inherit;
                transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
            }

            .ts-notification-entry:hover {
                transform: translateY(-1px);
                border-color: rgba(31, 111, 120, 0.25);
                background: #ffffff;
            }

            .ts-notification-entry-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 6px;
            }

            .ts-notification-entry strong {
                color: var(--ts-navy);
                font-size: 0.95rem;
                line-height: 1.35;
            }

            .ts-notification-entry time {
                color: #7b8a97;
                font-size: 0.76rem;
                white-space: nowrap;
            }

            .ts-notification-meta {
                color: #55697e;
                font-size: 0.84rem;
                line-height: 1.55;
            }

            .ts-notification-empty {
                padding: 18px 16px;
                border-radius: 18px;
                border: 1px dashed #c9d6e4;
                background: #f8fbfe;
                color: #6b7c8b;
                font-size: 0.98rem;
                line-height: 1.55;
            }

            .ts-menu a {
                display: block;
                padding: 11px 14px;
                border-radius: 10px;
                color: var(--ts-navy);
                text-decoration: none;
                font-size: 0.92rem;
                transition: background 0.2s ease, color 0.2s ease;
            }

            .ts-menu a:hover {
                background: rgba(31, 111, 120, 0.08);
            }

            .ts-menu .ts-accent-link {
                color: var(--ts-teal);
                font-weight: 800;
            }

            .ts-menu .ts-danger-link {
                color: #f87171;
            }

            .ts-user-trigger {
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }

            .ts-user-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: var(--ts-teal-soft);
                border: 1px solid rgba(31, 111, 120, 0.22);
                color: var(--ts-teal);
                font-weight: 800;
                flex-shrink: 0;
            }

            .ts-user-name {
                max-width: 140px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .ts-site-footer {
                margin-top: 56px;
                background: var(--ts-navy-deep);
                color: rgba(255, 255, 255, 0.72);
            }

            .ts-site-footer-inner {
                width: min(1320px, 94%);
                margin: 0 auto;
                padding: 28px 0 34px;
                display: grid;
                grid-template-columns: 1.3fr 1fr 1fr;
                gap: 24px;
            }

            .ts-site-footer h4 {
                color: #fff;
                margin-bottom: 10px;
                font-size: 0.98rem;
            }

            .ts-site-footer a {
                display: block;
                color: rgba(255, 255, 255, 0.72);
                text-decoration: none;
                margin-bottom: 8px;
            }

            .ts-site-footer a:hover {
                color: #fff;
            }

            .ts-site-footer-copy {
                width: min(1320px, 94%);
                margin: 0 auto;
                padding: 14px 0 20px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                font-size: 0.88rem;
            }

            .ts-chat-launcher {
                position: fixed;
                right: 22px;
                bottom: 22px;
                z-index: 11000;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 12px;
            }

            .ts-chat-panel {
                width: min(360px, calc(100vw - 28px));
                max-height: min(62vh, 520px);
                overflow: hidden;
                border-radius: 22px;
                background: #ffffff;
                border: 1px solid rgba(21, 50, 74, 0.12);
                box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
                display: none;
            }

            .ts-chat-panel.is-open {
                display: block;
            }

            .ts-chat-overlay {
                position: fixed;
                inset: 0;
                z-index: 12000;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 22px;
                background: rgba(15, 23, 42, 0.48);
                backdrop-filter: blur(6px);
            }

            .ts-chat-overlay.is-open {
                display: flex;
            }

            .ts-chat-modal {
                position: relative;
                width: min(920px, calc(100vw - 40px));
                height: min(88vh, 760px);
                border-radius: 24px;
                overflow: hidden;
                background: #ffffff;
                box-shadow: 0 32px 60px rgba(15, 23, 42, 0.26);
            }

            .ts-chat-loading {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(248, 251, 252, 0.92);
                color: var(--ts-navy);
                font-size: 1rem;
                font-weight: 800;
                letter-spacing: -0.02em;
                transition: opacity 0.2s ease, visibility 0.2s ease;
            }

            .ts-chat-loading.is-hidden {
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
            }

            .ts-chat-frame {
                width: 100%;
                height: 100%;
                border: none;
                background: #ffffff;
            }

            .ts-chat-panel-head {
                padding: 16px 18px 12px;
                border-bottom: 1px solid rgba(21, 50, 74, 0.08);
            }

            .ts-chat-panel-head strong {
                display: block;
                color: var(--ts-navy);
                font-size: 1rem;
            }

            .ts-chat-panel-head span {
                display: block;
                color: #6b7c8b;
                font-size: 0.86rem;
                margin-top: 4px;
            }

            .ts-chat-list {
                max-height: min(48vh, 420px);
                overflow-y: auto;
                padding: 8px;
            }

            .ts-chat-entry {
                display: block;
                padding: 12px;
                border-radius: 16px;
                text-decoration: none;
                color: inherit;
                border: 1px solid transparent;
                transition: background 0.2s ease, border-color 0.2s ease;
            }

            .ts-chat-entry:hover {
                background: rgba(31, 111, 120, 0.06);
                border-color: rgba(21, 50, 74, 0.08);
            }

            .ts-chat-entry-top {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 6px;
            }

            .ts-chat-entry strong {
                color: var(--ts-navy);
                font-size: 0.94rem;
            }

            .ts-chat-entry-time {
                color: #7b8a97;
                font-size: 0.76rem;
                white-space: nowrap;
            }

            .ts-chat-entry-sub {
                color: #44586d;
                font-size: 0.84rem;
                margin-bottom: 4px;
            }

            .ts-chat-entry-preview {
                color: #6b7c8b;
                font-size: 0.82rem;
                line-height: 1.45;
            }

            .ts-chat-entry-unread {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 20px;
                height: 20px;
                padding: 0 6px;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                font-size: 0.72rem;
                font-weight: 800;
                margin-left: 8px;
            }

            .ts-chat-empty-state {
                padding: 24px 18px 28px;
                color: #6b7c8b;
                font-size: 0.9rem;
                line-height: 1.55;
            }

            .ts-chat-fab {
                border: none;
                border-radius: 999px;
                background: linear-gradient(135deg, var(--ts-navy) 0%, var(--ts-teal) 100%);
                color: #fff;
                padding: 14px 18px;
                box-shadow: 0 20px 32px rgba(21, 50, 74, 0.26);
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-weight: 800;
                cursor: pointer;
            }

            .ts-chat-fab-badge {
                min-width: 22px;
                height: 22px;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                padding: 0 7px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 0.76rem;
                font-weight: 900;
            }

            @media (max-width: 1180px) {
                .ts-navbar {
                    grid-template-columns: 1fr;
                    gap: 14px;
                }

                .ts-brand {
                    justify-self: center;
                }

                .ts-nav-links {
                    justify-content: center;
                }
            }

            @media (max-width: 720px) {
                body {
                    padding-top: calc(var(--ts-nav-height) + 54px);
                }

                .ts-nav-shell {
                    top: 0;
                }

                .ts-navbar {
                    width: min(100% - 16px, 100%);
                    padding: 10px 0;
                }

                .ts-nav-links {
                    justify-content: flex-start;
                }

                .ts-link,
                .ts-dropdown summary {
                    font-size: 0.88rem;
                    padding: 9px 12px;
                }

                .ts-user-name {
                    max-width: 100px;
                }

                .ts-notification-menu {
                    min-width: min(320px, calc(100vw - 20px));
                    width: min(360px, calc(100vw - 20px));
                }

                .ts-site-footer-inner {
                    grid-template-columns: 1fr;
                }

                .ts-chat-overlay {
                    padding: 12px;
                }

                .ts-chat-modal {
                    width: 100%;
                    height: min(88vh, 100%);
                    border-radius: 18px;
                }

                .ts-chat-launcher {
                    right: 12px;
                    bottom: 12px;
                }

                .ts-chat-fab {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>
        <?php
    }

    function toolshare_render_nav(array $options = []): void
    {
        $searchValue = (string)($options['search_value'] ?? ($_GET['search'] ?? ''));
        $supportHref = (string)($options['support_href'] ?? 'index.php#support');
        if ($supportHref === '' || $supportHref === 'index.php#support') {
            $supportHref = 'support.php';
        }
        $isSignedIn = !empty($_SESSION['user_id']);
        $userName = $isSignedIn ? trim((string)($_SESSION['user_name'] ?? 'Account')) : 'Account';
        $initial = strtoupper(substr($userName, 0, 1)) ?: 'A';
        $notificationCenter = ['items' => [], 'unread_count' => 0, 'message_unread_count' => 0];
        $chatLauncher = ['recent_chats' => [], 'unread_count' => 0];
        if ($isSignedIn && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $notificationCenter = toolshare_fetch_user_notification_center($GLOBALS['pdo'], (int)$_SESSION['user_id'], 8);
            $chatLauncher = toolshare_fetch_chat_launcher_data();
        }
        ?>
        <div class="ts-nav-shell">
            <nav class="ts-navbar" aria-label="Primary">
                <a href="index.php" class="ts-brand">TOOLSHARE</a>

                <form class="ts-nav-search" action="browse.php" method="GET">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M20 20L16.65 16.65"></path>
                    </svg>
                    <input type="search" name="search" placeholder="Search drills, ladders, lawn care..." value="<?= htmlspecialchars($searchValue) ?>">
                </form>

                <div class="ts-nav-links">
                    <a href="index.php" class="ts-link">Home</a>
                    <a href="<?= htmlspecialchars($supportHref) ?>" class="ts-link">Customer Support</a>
                    <details class="ts-dropdown">
                        <summary>
                            <span class="ts-summary-inline">
                                <span>Browse Tools</span>
                                <span class="ts-caret" aria-hidden="true"></span>
                            </span>
                        </summary>
                        <div class="ts-menu">
                            <a href="browse.php?category=Power+Tools">Power Tools</a>
                            <a href="browse.php?category=Gardening">Gardening Tools</a>
                            <a href="browse.php?search=Cleaning+Equipment">Cleaning Equipment</a>
                            <a href="browse.php">View All</a>
                            <a href="add_tool.php" class="ts-accent-link">List a Tool</a>
                        </div>
                    </details>

                    <?php if ($isSignedIn): ?>
                        <details class="ts-dropdown">
                            <summary class="ts-icon-link" aria-label="Notifications">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"></path><path d="M10 21a2 2 0 0 0 4 0"></path></svg>
                                <?php if ((int)$notificationCenter['unread_count'] > 0): ?><span class="ts-unread-dot" aria-hidden="true"></span><?php endif; ?>
                            </summary>
                            <div class="ts-menu ts-notification-menu">
                                <div class="ts-notification-head">
                                    <strong>Notifications</strong>
                                    <span class="ts-notification-count"><?= number_format((int)$notificationCenter['unread_count']) ?> active</span>
                                </div>
                                <?php if (empty($notificationCenter['items'])): ?>
                                    <div class="ts-notification-empty">No active notifications right now.</div>
                                <?php else: ?>
                                    <div class="ts-notification-list">
                                        <?php foreach ($notificationCenter['items'] as $item): ?>
                                            <?php
                                                $notificationTarget = (string)($item['href'] ?? 'dashboard.php');
                                                $notificationHref = 'notification_redirect.php?' . http_build_query([
                                                    'type' => (string)($item['type'] ?? ''),
                                                    'item_key' => (string)($item['item_key'] ?? ''),
                                                    'to' => $notificationTarget,
                                                ]);
                                            ?>
                                            <a href="<?= htmlspecialchars($notificationHref) ?>" class="ts-notification-entry">
                                                <div class="ts-notification-entry-top">
                                                    <strong><?= htmlspecialchars((string)$item['label']) ?></strong>
                                                    <time datetime="<?= htmlspecialchars((string)$item['created_at']) ?>"><?= date('M d, h:i A', strtotime((string)$item['created_at'])) ?></time>
                                                </div>
                                                <div class="ts-notification-meta"><?= htmlspecialchars((string)$item['meta']) ?></div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endif; ?>

                    <?php if ($isSignedIn): ?>
                        <details class="ts-dropdown">
                            <summary>
                                <span class="ts-user-trigger">
                                    <span class="ts-user-avatar"><?= htmlspecialchars($initial) ?></span>
                                    <span class="ts-user-name"><?= htmlspecialchars($userName) ?></span>
                                    <span class="ts-caret" aria-hidden="true"></span>
                                </span>
                            </summary>
                            <div class="ts-menu">
                                <a href="dashboard.php">Dashboard</a>
                                <a href="my_earnings.php">My Earnings</a>
                                <a href="profile.php">View Profile</a>
                                <a href="add_tool.php" class="ts-accent-link">List a Tool</a>
                                <a href="logout.php" class="ts-danger-link">Logout</a>
                            </div>
                        </details>
                    <?php else: ?>
                        <a href="login.php" class="ts-link">Sign In</a>
                        <a href="register.php" class="ts-link">Create Account</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
        <?php
    }

    function toolshare_render_focus_header(array $options = []): void
    {
        $title = trim((string)($options['title'] ?? 'Focused Flow'));
        $kicker = trim((string)($options['kicker'] ?? 'ToolShare'));
        $backHref = trim((string)($options['back_href'] ?? 'dashboard.php'));
        $backLabel = trim((string)($options['back_label'] ?? 'Back'));
        ?>
        <div class="ts-focus-shell">
            <div class="ts-focus-bar">
                <div class="ts-focus-copy">
                    <span class="ts-focus-kicker"><?= htmlspecialchars($kicker) ?></span>
                    <span class="ts-focus-title"><?= htmlspecialchars($title) ?></span>
                </div>
                <a href="<?= htmlspecialchars($backHref) ?>" class="ts-focus-link"><?= htmlspecialchars($backLabel) ?></a>
            </div>
        </div>
        <?php
    }

    function toolshare_fetch_chat_launcher_data(): array
    {
        if (empty($_SESSION['user_id'])) {
            return ['recent_chats' => [], 'unread_count' => 0];
        }

        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return ['recent_chats' => [], 'unread_count' => 0];
        }

        $userId = (int) $_SESSION['user_id'];

        $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmtUnread->execute([$userId]);
        $unreadCount = (int) $stmtUnread->fetchColumn() + toolshare_support_count_unread($pdo, $userId);

        $stmtChats = $pdo->prepare("
            SELECT
                m.*,
                t.title AS tool_title,
                u_sender.full_name AS sender_name,
                u_receiver.full_name AS receiver_name,
                (
                    SELECT COUNT(*)
                    FROM messages m2
                    WHERE m2.tool_id = m.tool_id
                      AND m2.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
                      AND m2.receiver_id = ?
                      AND m2.is_read = 0
                ) AS unread_in_thread
            FROM messages m
            JOIN tools t ON m.tool_id = t.id
            JOIN users u_sender ON m.sender_id = u_sender.id
            JOIN users u_receiver ON m.receiver_id = u_receiver.id
            WHERE m.id IN (
                SELECT MAX(id)
                FROM messages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY tool_id, LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
            )
            ORDER BY m.created_at DESC
            LIMIT 8
        ");
        $stmtChats->execute([$userId, $userId, $userId, $userId]);
        $recentChats = $stmtChats->fetchAll();

        foreach (toolshare_support_fetch_recent_threads($pdo, $userId, 4) as $thread) {
            $recentChats[] = [
                'thread_type' => 'support',
                'sender_id' => 0,
                'receiver_id' => $userId,
                'sender_name' => $thread['name'],
                'receiver_name' => '',
                'tool_title' => $thread['subtitle'],
                'message_text' => $thread['preview'],
                'created_at' => $thread['created_at'],
                'unread_in_thread' => $thread['unread_count'],
                'href' => $thread['href'],
            ];
        }

        usort($recentChats, static function (array $a, array $b): int {
            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });
        $recentChats = array_slice($recentChats, 0, 8);

        return ['recent_chats' => $recentChats, 'unread_count' => $unreadCount];
    }

    function toolshare_render_chrome_scripts(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        $currentFile = basename($_SERVER['PHP_SELF'] ?? '');
        $footerExcluded = ['login.php', 'register.php', 'chat.php', 'booking_confirmation.php', 'payment_success.php'];
        ?>
        <?php if (!in_array($currentFile, $footerExcluded, true)): ?>
            <footer class="ts-site-footer">
                <div class="ts-site-footer-inner">
                    <div>
                        <h4>ToolShare</h4>
                        <p>Neighborhood tool rentals with simple booking, clear pricing, and direct owner coordination.</p>
                    </div>
                    <div>
                        <h4>Explore</h4>
                        <a href="index.php">Home</a>
                        <a href="browse.php">Browse Tools</a>
                        <a href="add_tool.php">List a Tool</a>
                    </div>
                    <div>
                        <h4>Account</h4>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="active_rentals.php">Active Rentals</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
                <div class="ts-site-footer-copy">
                    © 2026 ToolShare. Built for practical local rentals.
                </div>
            </footer>
        <?php endif; ?>
        <?php $chatLauncher = toolshare_fetch_chat_launcher_data(); ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <div class="ts-chat-launcher" id="tsChatLauncher">
                <div class="ts-chat-panel" id="tsChatPanel">
                    <div class="ts-chat-panel-head">
                        <strong>Messages</strong>
                        <span>Open any conversation without leaving this page.</span>
                    </div>
                    <?php if (empty($chatLauncher['recent_chats'])): ?>
                        <div class="ts-chat-empty-state">
                            No conversations yet. Use any `Message Owner` or `Message Renter` button to start chatting.
                        </div>
                    <?php else: ?>
                        <div class="ts-chat-list">
                            <?php foreach ($chatLauncher['recent_chats'] as $chat): ?>
                                <?php
                                    $isSupportThread = (($chat['thread_type'] ?? 'chat') === 'support');
                                    $otherId = ((int) $chat['sender_id'] === (int) $_SESSION['user_id']) ? (int) $chat['receiver_id'] : (int) $chat['sender_id'];
                                    $otherName = $isSupportThread
                                        ? (string) $chat['sender_name']
                                        : (((int) $chat['sender_id'] === (int) $_SESSION['user_id']) ? (string) $chat['receiver_name'] : (string) $chat['sender_name']);
                                    $preview = mb_strlen((string) $chat['message_text']) > 68 ? mb_substr((string) $chat['message_text'], 0, 68) . '...' : (string) $chat['message_text'];
                                    $href = $isSupportThread
                                        ? (string) ($chat['href'] ?? 'support.php')
                                        : 'chat.php?tool_id=' . (int) $chat['tool_id'] . '&receiver_id=' . $otherId;
                                ?>
                                <a href="<?= htmlspecialchars($href) ?>" class="ts-chat-entry">
                                    <div class="ts-chat-entry-top">
                                        <strong><?= htmlspecialchars($otherName) ?><?php if ((int) $chat['unread_in_thread'] > 0): ?><span class="ts-chat-entry-unread"><?= (int) $chat['unread_in_thread'] ?></span><?php endif; ?></strong>
                                        <span class="ts-chat-entry-time"><?= date('M d', strtotime((string) $chat['created_at'])) ?></span>
                                    </div>
                                    <div class="ts-chat-entry-sub"><?= htmlspecialchars((string) $chat['tool_title']) ?></div>
                                    <div class="ts-chat-entry-preview"><?= htmlspecialchars($preview) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ts-chat-overlay" id="tsChatOverlay" aria-hidden="true">
                    <div class="ts-chat-modal" role="dialog" aria-modal="true" aria-label="Messages">
                        <div class="ts-chat-loading" id="tsChatLoading">Opening conversation...</div>
                        <iframe id="tsChatFrame" class="ts-chat-frame" title="ToolShare chat"></iframe>
                    </div>
                </div>
                <button type="button" class="ts-chat-fab" id="tsChatFab" aria-expanded="false" aria-controls="tsChatPanel">
                    <span>Messages</span>
                    <?php if ((int) $chatLauncher['unread_count'] > 0): ?>
                        <span class="ts-chat-fab-badge"><?= (int) $chatLauncher['unread_count'] ?></span>
                    <?php endif; ?>
                </button>
            </div>
        <?php endif; ?>
        <script>
            const tsChatFab = document.getElementById('tsChatFab');
            const tsChatPanel = document.getElementById('tsChatPanel');
            const tsChatOverlay = document.getElementById('tsChatOverlay');
            const tsChatFrame = document.getElementById('tsChatFrame');
            const tsChatLoading = document.getElementById('tsChatLoading');

            if (tsChatFab && tsChatPanel) {
                tsChatFab.addEventListener('click', function () {
                    const isOpen = tsChatPanel.classList.toggle('is-open');
                    tsChatFab.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            }

            function toolshareOpenChatOverlay(href) {
                if (!tsChatOverlay || !tsChatFrame || !tsChatLoading) {
                    window.location.href = href;
                    return;
                }

                const url = new URL(href, window.location.href);
                if (!url.searchParams.has('embed')) {
                    url.searchParams.set('embed', '1');
                }

                tsChatLoading.classList.remove('is-hidden');
                tsChatOverlay.classList.add('is-open');
                tsChatOverlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                tsChatFrame.src = url.toString();
            }

            function toolshareCloseChatOverlay() {
                if (!tsChatOverlay || !tsChatFrame || !tsChatLoading) {
                    return;
                }

                tsChatOverlay.classList.remove('is-open');
                tsChatOverlay.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                tsChatFrame.src = 'about:blank';
                tsChatLoading.classList.remove('is-hidden');
            }

            if (tsChatFrame && tsChatLoading) {
                tsChatFrame.addEventListener('load', function () {
                    tsChatLoading.classList.add('is-hidden');
                });
            }

            document.addEventListener('click', function (event) {
                document.querySelectorAll('.ts-dropdown[open]').forEach(function (dropdown) {
                    if (!dropdown.contains(event.target)) {
                        dropdown.removeAttribute('open');
                    }
                });

                if (tsChatPanel && tsChatFab) {
                    const launcher = document.getElementById('tsChatLauncher');
                    if (launcher && !launcher.contains(event.target)) {
                        tsChatPanel.classList.remove('is-open');
                        tsChatFab.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            document.addEventListener('click', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                const chatLink = event.target.closest('a[href]');
                if (chatLink) {
                    const chatHref = chatLink.getAttribute('href') || '';
                    if (chatHref.indexOf('chat.php') !== -1 || chatHref.indexOf('support_chat.php') !== -1) {
                        event.preventDefault();
                        if (tsChatPanel && tsChatFab) {
                            tsChatPanel.classList.remove('is-open');
                            tsChatFab.setAttribute('aria-expanded', 'false');
                        }
                        toolshareOpenChatOverlay(chatLink.href);
                        return;
                    }
                }
            });

            if (tsChatOverlay) {
                tsChatOverlay.addEventListener('click', function (event) {
                    if (event.target === tsChatOverlay) {
                        toolshareCloseChatOverlay();
                    }
                });
            }

            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin || !event.data || event.data.type !== 'toolshare-close-chat') {
                    return;
                }
                toolshareCloseChatOverlay();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && tsChatOverlay && tsChatOverlay.classList.contains('is-open')) {
                    toolshareCloseChatOverlay();
                }
            });

            document.addEventListener('click', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                const link = event.target.closest('a[href]');
                if (!link) {
                    return;
                }

                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || link.target === '_blank' || link.hasAttribute('download')) {
                    return;
                }

                const url = new URL(link.href, window.location.href);
                if (url.origin !== window.location.origin) {
                    return;
                }

                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                event.preventDefault();
                document.body.classList.add('ts-page-leaving');
                window.setTimeout(function () {
                    window.location.href = url.href;
                }, 180);
            });
        </script>
        <?php
    }
}
