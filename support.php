<?php
session_start();
require 'config/db.php';
require 'includes/site_chrome.php';
require 'includes/auth.php';
require 'includes/support_helpers.php';

toolshare_enforce_session_account_state();

$isSignedIn = isset($_SESSION['user_id']);
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = trim((string)($_SESSION['user_name'] ?? ''));
$role = toolshare_current_role();
$dashboardHref = $isSignedIn ? toolshare_dashboard_link() : 'login.php';
$supportError = '';

if ($isSignedIn && in_array($role, ['user', 'owner_admin'], true)) {
    toolshare_support_mark_user_messages_read($pdo, $userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $message === '') {
        $supportError = 'Please complete your name, email, and message before submitting.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $supportError = 'Please enter a valid email address.';
    } else {
        try {
            $ticketId = toolshare_support_submit_ticket($pdo, [
                'user_id' => $isSignedIn ? $userId : null,
                'name' => $name,
                'email' => $email,
                'message' => $message,
            ]);
            header('Location: support.php?' . http_build_query([
                'msg' => 'ticket_submitted',
                'ticket_id' => $ticketId,
            ]) . '#ticket-' . $ticketId);
            exit;
        } catch (Throwable $e) {
            $supportError = 'Unable to submit your ticket right now. Please try again.';
        }
    }
}

$flashMessage = '';
if (($_GET['msg'] ?? '') === 'ticket_submitted') {
    $flashMessage = 'Your support ticket was submitted successfully. ToolShare Support will review it and reply in your support messages.';
}

$stats = [
    'unread_messages' => 0,
    'open_tickets' => 0,
    'open_disputes' => 0,
    'available_receipts' => 0,
];

if ($isSignedIn && in_array($role, ['user', 'owner_admin'], true)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $stats['unread_messages'] = (int)$stmt->fetchColumn() + toolshare_support_count_unread($pdo, $userId);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM support_tickets
        WHERE user_id = ?
          AND status = 'open'
    ");
    $stmt->execute([$userId]);
    $stats['open_tickets'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM disputes
        WHERE (renter_id = ? OR owner_id = ?)
          AND status IN ('pending', 'reviewing')
    ");
    $stmt->execute([$userId, $userId]);
    $stats['open_disputes'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE (renter_id = ? OR owner_id = ?)
          AND status = 'completed'
    ");
    $stmt->execute([$userId, $userId]);
    $stats['available_receipts'] = (int)$stmt->fetchColumn();
}

$quickLinks = [];
if (!$isSignedIn) {
    $quickLinks = [
        ['title' => 'Sign In', 'desc' => 'Open your dashboard, messages, disputes, and receipts.', 'href' => 'login.php', 'label' => 'Sign In'],
        ['title' => 'Browse Tools', 'desc' => 'Check listing details, prices, and owner information before booking.', 'href' => 'browse.php', 'label' => 'Browse Tools'],
        ['title' => 'Read FAQs', 'desc' => 'Find quick answers before opening a support ticket.', 'href' => '#support-faq', 'label' => 'View FAQs'],
    ];
} else {
    $quickLinks = [
        ['title' => 'Open Dashboard', 'desc' => 'Check bookings, receipts, returns, and dispute status.', 'href' => $dashboardHref, 'label' => 'Open Dashboard'],
        ['title' => 'Messages', 'desc' => 'Use in-app chat for owner and renter coordination.', 'href' => $dashboardHref, 'label' => 'Open Messages'],
        ['title' => 'Browse Tools', 'desc' => 'View listings, pricing, and owner information.', 'href' => 'browse.php', 'label' => 'Browse Tools'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root {
            --support-navy: #15324a;
            --support-teal: #1f6f78;
            --support-surface: #f4f8fb;
            --support-card: rgba(255, 255, 255, 0.92);
            --support-line: rgba(148, 163, 184, 0.22);
            --support-muted: #64748b;
            --support-shadow: 0 24px 54px rgba(15, 23, 42, 0.08);
        }
        * { box-sizing: border-box; font-family: "Avenir Next", "Segoe UI", sans-serif; }
        body {
            background:
                radial-gradient(circle at top right, rgba(31, 111, 120, 0.12), transparent 24%),
                linear-gradient(180deg, #eef5f8 0%, #f7fafc 100%);
            color: #102338;
        }
        .support-shell { width: min(1240px, 94%); margin: 0 auto; padding: 24px 0 56px; }
        .support-hero, .support-card, .support-faq, .support-ticket-panel, .support-thread-panel {
            background: var(--support-card);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 28px;
            box-shadow: var(--support-shadow);
            backdrop-filter: blur(14px);
        }
        .support-hero {
            padding: 34px;
            background:
                linear-gradient(135deg, rgba(16, 35, 56, 0.98), rgba(20, 59, 77, 0.92)),
                radial-gradient(circle at top right, rgba(31, 111, 120, 0.28), transparent 30%);
            color: white;
            margin-bottom: 26px;
        }
        .support-kicker {
            display: inline-flex;
            padding: 9px 15px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.14);
            font-size: 0.84rem;
            font-weight: 800;
            margin-bottom: 18px;
        }
        .support-hero h1 { font-size: clamp(2.2rem, 5vw, 3.2rem); line-height: 1; letter-spacing: -0.06em; margin-bottom: 14px; }
        .support-hero p { color: rgba(255,255,255,0.84); line-height: 1.7; max-width: 760px; }
        .support-actions { display:flex; flex-wrap:wrap; gap:14px; margin-top:22px; }
        .support-btn, .support-btn-secondary {
            display:inline-flex; align-items:center; justify-content:center; min-height:52px; padding:0 20px;
            border-radius:999px; text-decoration:none; font-weight:800;
        }
        .support-btn { background:#7ce2e7; color:#102338; }
        .support-btn-secondary { background:rgba(255,255,255,0.08); color:white; border:1px solid rgba(255,255,255,0.18); }
        .support-alert { margin: 0 0 20px; padding: 14px 16px; border-radius: 16px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #166534; }
        .support-error { margin: 0 0 18px; padding: 14px 16px; border-radius: 16px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .section-title { margin: 20px 4px 18px; }
        .section-title h2 { color: var(--support-navy); font-size: 1.9rem; letter-spacing: -0.05em; margin-bottom: 6px; }
        .section-title p { color: var(--support-muted); max-width: 760px; line-height: 1.7; }
        .support-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:18px; margin-bottom:26px; }
        .support-card { padding: 24px; }
        .support-card h3 { color: var(--support-navy); font-size: 1.25rem; margin-bottom: 8px; }
        .support-card p { color: var(--support-muted); line-height: 1.72; margin-bottom: 16px; }
        .support-meta { display:inline-flex; padding:8px 12px; border-radius:999px; background:#dff1f3; color:var(--support-teal); font-size:0.82rem; font-weight:800; margin-bottom:14px; }
        .support-link { color: var(--support-teal); text-decoration:none; font-weight:800; }
        .support-ticket-panel, .support-thread-panel, .support-faq { padding: 28px; margin-bottom: 26px; }
        .support-form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:16px; }
        .support-form-grid .full { grid-column: 1 / -1; }
        label { display:block; margin-bottom:8px; color:#64748b; font-size:12px; text-transform:uppercase; font-weight:800; }
        input, textarea {
            width:100%; padding:12px 14px; border:1px solid #dbe3ee; border-radius:14px; font-size:14px; background:#fff;
        }
        textarea { min-height: 150px; resize: vertical; }
        .support-submit { margin-top: 18px; }
        .support-stats { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:14px; margin-bottom: 26px; }
        .support-stat-item { padding: 18px; border-radius: 20px; background: #fff; border:1px solid var(--support-line); }
        .support-stat-item strong { display:block; font-size:1.45rem; color:var(--support-navy); margin-bottom:4px; }
        .support-stat-item span { color: var(--support-muted); font-weight:700; font-size:0.92rem; }
        .support-thread-list { display:grid; gap:14px; }
        .support-thread { padding:18px; border-radius:20px; background:#f8fcfd; border:1px solid var(--support-line); }
        .support-thread-head { display:flex; justify-content:space-between; gap:12px; margin-bottom:10px; }
        .support-thread-head strong { color: var(--support-navy); }
        .support-thread-head span { color: var(--support-muted); font-size: 0.9rem; }
        .support-thread p { color: var(--support-muted); line-height: 1.7; margin-bottom: 10px; }
        .support-thread-meta { display:flex; flex-wrap:wrap; gap:10px; }
        .support-pill { display:inline-flex; padding:6px 10px; border-radius:999px; background:#eef6ff; color:#1d4ed8; font-size:12px; font-weight:800; }
        .support-pill.warning { background:#fff7ed; color:#b45309; }
        .support-thread-link { text-decoration:none; color:inherit; display:block; }
        .support-faq-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:16px; }
        .support-faq-item { padding:18px 20px; border-radius:20px; border:1px solid var(--support-line); background:#fbfdfe; }
        .support-faq-item h3 { color: var(--support-navy); font-size: 1.02rem; margin-bottom: 10px; line-height: 1.4; }
        .support-faq-item p { color:var(--support-muted); line-height:1.72; margin:0; }
        @media (max-width: 1080px) {
            .support-grid, .support-form-grid, .support-stats, .support-faq-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php toolshare_render_nav(['support_href' => 'support.php']); ?>

<main class="support-shell">
    <section class="support-hero">
        <div class="support-kicker">Customer Support Center</div>
        <h1>Get help with bookings, payments, receipts, disputes, and account issues.</h1>
        <p>
            Use this page when you need help from ToolShare Support. For owner or renter coordination, keep using in-app chat. For booking problems that need formal review, use the dispute flow. For everything else, raise a support ticket below.
        </p>
        <div class="support-actions">
            <a href="<?= htmlspecialchars($dashboardHref) ?>" class="support-btn"><?= $isSignedIn ? 'Open My Dashboard' : 'Sign In For Support' ?></a>
            <a href="browse.php" class="support-btn-secondary">Browse Tools</a>
        </div>
    </section>

    <?php if ($flashMessage !== ''): ?>
        <div class="support-alert"><?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>

    <?php if ($isSignedIn && in_array($role, ['user', 'owner_admin'], true)): ?>
        <section class="support-stats">
            <div class="support-stat-item"><strong><?= number_format($stats['unread_messages']) ?></strong><span>Unread messages and support replies</span></div>
            <div class="support-stat-item"><strong><?= number_format($stats['open_tickets']) ?></strong><span>Open support tickets</span></div>
            <div class="support-stat-item"><strong><?= number_format($stats['open_disputes']) ?></strong><span>Open disputes</span></div>
            <div class="support-stat-item"><strong><?= number_format($stats['available_receipts']) ?></strong><span>Completed bookings / receipts</span></div>
        </section>
    <?php endif; ?>

    <section>
        <div class="section-title">
            <h2>Quick actions</h2>
            <p>Use these shortcuts first if your question is already covered by a normal ToolShare workflow.</p>
        </div>
        <div class="support-grid">
            <?php foreach ($quickLinks as $link): ?>
                <article class="support-card">
                    <div class="support-meta">Quick Action</div>
                    <h3><?= htmlspecialchars($link['title']) ?></h3>
                    <p><?= htmlspecialchars($link['desc']) ?></p>
                    <a href="<?= htmlspecialchars($link['href']) ?>" class="support-link"><?= htmlspecialchars($link['label']) ?> -></a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="support-ticket-panel">
        <div class="section-title" style="margin:0 0 16px;">
            <h2>Raise a support ticket</h2>
            <p>Use this form for payment questions, account help, technical issues, refund follow-up, or general platform support. Do not use it for owner-return damage claims or renter pickup problems that should go through the dispute workflow.</p>
        </div>
        <?php if ($supportError !== ''): ?>
            <div class="support-error"><?= htmlspecialchars($supportError) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="support-form-grid">
                <div>
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars((string)($_POST['name'] ?? ($isSignedIn ? $userName : ''))) ?>" required>
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>" required>
                </div>
                <div class="full">
                    <label>Message</label>
                    <textarea name="message" placeholder="Describe your issue clearly so ToolShare Support can help you faster." required><?= htmlspecialchars((string)($_POST['message'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="support-submit">
                <button type="submit" class="support-btn" style="border:none; cursor:pointer;">Submit Ticket</button>
            </div>
        </form>
    </section>

    <section class="support-faq" id="support-faq">
        <div class="section-title" style="margin:0 0 18px;">
            <h2>Common support questions</h2>
            <p>Quick answers to the questions users most often ask.</p>
        </div>

        <div class="support-faq-grid">
            <article class="support-faq-item">
                <h3>How do I contact the tool owner or renter?</h3>
                <p>Use the in-app chat from the tool page, your dashboard, or the active rental workflow. That keeps the conversation linked to the booking, makes pickup and return coordination easier, and gives operations a clearer history if the issue later needs review.</p>
            </article>
            <article class="support-faq-item">
                <h3>Where do I see my payment and receipt?</h3>
                <p>You can see payment-related details from your dashboard. Renters can download their receipt after payment and again after the booking is completed, while owners can download an earnings statement once the rental has been settled.</p>
            </article>
            <article class="support-faq-item">
                <h3>What happens if I report a problem or a return is disputed?</h3>
                <p>If a renter reports a pickup or service problem, or if an owner disputes the condition of a returned tool, the case is sent to the operations team for review. Operations can check the evidence, contact both sides if needed, and then record the final outcome such as refund approval, partial adjustment, or case closure.</p>
            </article>
            <article class="support-faq-item">
                <h3>When should I use a support ticket instead of a dispute?</h3>
                <p>Use a support ticket when you need help with account access, payment questions, refund follow-up, technical problems, or general platform support. Use a dispute when the issue is a formal booking-related claim, such as a renter not receiving the tool, receiving a damaged tool at pickup, or an owner reporting a return-condition problem.</p>
            </article>
        </div>
    </section>
</main>

<?php toolshare_render_chrome_scripts(); ?>
</body>
</html>
