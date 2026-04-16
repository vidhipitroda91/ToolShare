<?php
session_start();
require 'config/db.php';
require 'includes/support_helpers.php';
require 'includes/admin_layout.php';

toolshare_require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
    $reply = trim((string)($_POST['reply_message'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'open'));

    try {
        toolshare_support_reply_ticket($pdo, $ticketId, (int)($_SESSION['user_id'] ?? 0), $reply, $status);
        header('Location: admin_tickets.php?' . http_build_query([
            'ticket_id' => $ticketId,
            'status_filter' => $_GET['status_filter'] ?? '',
            'search' => $_GET['search'] ?? '',
            'msg' => 'Support ticket reply sent.',
        ]));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$statusFilter = trim((string)($_GET['status_filter'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$selectedId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

$tickets = toolshare_support_fetch_admin_tickets($pdo, [
    'status' => $statusFilter,
    'search' => $search,
]);
$selectedTicket = $selectedId > 0 ? toolshare_support_fetch_admin_ticket($pdo, $selectedId) : null;
if (!$selectedTicket && !empty($tickets)) {
    $selectedTicket = $tickets[0];
}
if ($selectedTicket) {
    toolshare_mark_notification_item_viewed($pdo, (int)($_SESSION['user_id'] ?? 0), 'admin', 'ticket', (string)(int)$selectedTicket['id']);
}
$ticketMessages = $selectedTicket ? toolshare_support_fetch_ticket_messages($pdo, (int)$selectedTicket['id']) : [];

$summary = [
    'open' => 0,
    'resolved' => 0,
];
foreach ($tickets as $ticket) {
    $statusValue = (string)$ticket['status'];
    if (isset($summary[$statusValue])) {
        $summary[$statusValue]++;
    }
}

toolshare_admin_render_layout_start($pdo, 'Support Tickets', 'tickets', 'Review support requests, reply as ToolShare Support, and keep the user updated from one queue.');
?>

<style>
    .ticket-workspace { display: grid; gap: 22px; }
    .ticket-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .ticket-kpi { border: 1px solid var(--admin-line); border-radius: 20px; padding: 18px; background: #fff; }
    .ticket-kpi strong { display:block; color:var(--admin-muted); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:10px; }
    .ticket-kpi span { display:block; font-size:1.9rem; font-weight:900; color:var(--admin-navy); }
    .ticket-layout { display:grid; grid-template-columns:minmax(320px,0.9fr) minmax(0,1.3fr); gap:20px; align-items:start; }
    .ticket-list { display:grid; gap:12px; }
    .ticket-card { display:block; text-decoration:none; color:inherit; border:1px solid var(--admin-line); border-radius:18px; padding:16px; background:#fff; }
    .ticket-card:hover, .ticket-card.active { border-color:rgba(31,122,134,0.35); box-shadow:0 14px 30px rgba(15,23,42,0.06); }
    .ticket-card-head { display:flex; justify-content:space-between; gap:10px; margin-bottom:8px; align-items:center; }
    .ticket-card-title { font-size:1.1rem; font-weight:900; color:var(--admin-navy); }
    .ticket-card p { color:var(--admin-muted); line-height:1.55; font-size:0.93rem; }
    .ticket-panel { display:grid; gap:16px; align-self:start; }
    .ticket-box { border:1px solid var(--admin-line); border-radius:20px; padding:18px; background:#fff; }
    .ticket-summary-card { display:grid; gap:14px; }
    .ticket-summary-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .ticket-summary-head h2 { margin:0; color:var(--admin-navy); }
    .ticket-summary-meta { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
    .ticket-meta-pill { border:1px solid var(--admin-line); border-radius:16px; padding:12px 14px; background:#f8fafc; min-width:0; }
    .ticket-meta-pill strong { display:block; color:var(--admin-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:5px; }
    .ticket-meta-pill span { display:block; color:var(--admin-navy); font-weight:800; overflow-wrap:anywhere; }
    .ticket-thread-shell { display:grid; gap:12px; min-height:440px; }
    .ticket-thread-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .ticket-thread-head h3 { margin:0; color:var(--admin-navy); }
    .ticket-thread-count { color:var(--admin-muted); font-size:0.92rem; font-weight:700; }
    .ticket-thread { display:grid; align-content:start; gap:12px; max-height:480px; overflow-y:auto; padding-right:8px; }
    .ticket-thread::-webkit-scrollbar { width:8px; }
    .ticket-thread::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:999px; }
    .ticket-bubble { padding:14px 16px; border-radius:18px; background:#f8fafc; border:1px solid #e2e8f0; max-width:84%; }
    .ticket-bubble.user { justify-self:start; }
    .ticket-bubble.support { justify-self:end; background:#eff6ff; border-color:#bfdbfe; }
    .ticket-bubble strong { display:block; color:var(--admin-navy); margin-bottom:6px; }
    .ticket-bubble small { display:block; color:var(--admin-muted); margin-top:8px; }
    .ticket-action-box { display:grid; gap:12px; }
    .ticket-action-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .ticket-action-head h3 { margin:0; color:var(--admin-navy); }
    .ticket-closed-note { border:1px dashed #cbd5e1; border-radius:16px; padding:14px 16px; color:var(--admin-muted); background:#f8fafc; }
    @media (max-width: 1100px) {
        .ticket-kpis, .ticket-layout, .admin-filter-row.cols-2 { grid-template-columns: 1fr; }
        .ticket-summary-meta { grid-template-columns:1fr; }
        .ticket-bubble { max-width:100%; }
        .ticket-thread { max-height:none; }
    }
</style>

<div class="ticket-workspace">
    <section class="ticket-kpis">
        <div class="ticket-kpi"><strong>Open</strong><span><?= number_format($summary['open']) ?></span></div>
        <div class="ticket-kpi"><strong>Closed</strong><span><?= number_format($summary['resolved']) ?></span></div>
    </section>

    <section class="admin-card">
        <div class="admin-card-header">
            <h2>Support Ticket Queue</h2>
        </div>
        <?php if ($error !== ''): ?>
            <div class="admin-alert" style="margin-bottom:18px; background:#fef2f2; border-color:#fecaca; color:#991b1b;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="GET" class="admin-filter-row">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ticket ID, customer name, email, message">
            <select name="status_filter">
                <option value="">All Statuses</option>
                <?php foreach (['open', 'resolved'] as $item): ?>
                    <option value="<?= $item ?>" <?= $statusFilter === $item ? 'selected' : '' ?>><?= htmlspecialchars(toolshare_support_status_label($item)) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="admin_tickets.php" class="admin-btn secondary">Reset</a>
            <button type="submit" class="admin-btn">Filter</button>
        </form>

        <div class="ticket-layout">
            <div class="ticket-list">
                <?php if (empty($tickets)): ?>
                    <div class="admin-note">No support tickets matched the current filters.</div>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                        $query = ['ticket_id' => (int)$ticket['id']];
                        if ($statusFilter !== '') {
                            $query['status_filter'] = $statusFilter;
                        }
                        if ($search !== '') {
                            $query['search'] = $search;
                        }
                        ?>
                        <a href="admin_tickets.php?<?= htmlspecialchars(http_build_query($query)) ?>" class="ticket-card <?= $selectedTicket && (int)$selectedTicket['id'] === (int)$ticket['id'] ? 'active' : '' ?>">
                            <div class="ticket-card-head">
                                <div class="ticket-card-title">Ticket #<?= (int)$ticket['id'] ?></div>
                                <span class="admin-badge-pill <?= $ticket['status'] === 'resolved' ? 'success' : 'warning' ?>"><?= htmlspecialchars(toolshare_support_status_label((string)$ticket['status'])) ?></span>
                            </div>
                            <p><strong><?= htmlspecialchars((string)$ticket['name']) ?></strong> · <?= htmlspecialchars((string)$ticket['email']) ?></p>
                            <p><?= htmlspecialchars(mb_strlen((string)($ticket['last_message_text'] ?? '')) > 90 ? mb_substr((string)$ticket['last_message_text'], 0, 90) . '...' : (string)($ticket['last_message_text'] ?? '')) ?></p>
                            <p><?= !empty($ticket['last_message_at']) ? date('M d, Y h:i A', strtotime((string)$ticket['last_message_at'])) : date('M d, Y h:i A', strtotime((string)$ticket['created_at'])) ?><?php if ((int)$ticket['unread_count'] > 0): ?> · <?= (int)$ticket['unread_count'] ?> unread from user<?php endif; ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="ticket-panel">
                <?php if (!$selectedTicket): ?>
                    <div class="admin-note">Select a support ticket to review the conversation and send a reply.</div>
                <?php else: ?>
                    <div class="ticket-box ticket-summary-card">
                        <div class="ticket-summary-head">
                            <div>
                                <h2>Ticket #<?= (int)$selectedTicket['id'] ?></h2>
                                <p style="margin:6px 0 0; color:var(--admin-muted);">Review the conversation, decide the next step, and keep the thread concise for the customer.</p>
                            </div>
                            <span class="admin-badge-pill <?= $selectedTicket['status'] === 'resolved' ? 'success' : 'warning' ?>"><?= htmlspecialchars(toolshare_support_status_label((string)$selectedTicket['status'])) ?></span>
                        </div>
                        <div class="ticket-summary-meta">
                            <div class="ticket-meta-pill">
                                <strong>Customer</strong>
                                <span><?= htmlspecialchars((string)$selectedTicket['name']) ?></span>
                            </div>
                            <div class="ticket-meta-pill">
                                <strong>Email</strong>
                                <span><?= htmlspecialchars((string)$selectedTicket['email']) ?></span>
                            </div>
                            <div class="ticket-meta-pill">
                                <strong>Opened</strong>
                                <span><?= date('M d, Y h:i A', strtotime((string)$selectedTicket['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="ticket-box ticket-thread-shell">
                        <div class="ticket-thread-head">
                            <h3>Conversation</h3>
                            <span class="ticket-thread-count"><?= number_format(count($ticketMessages)) ?> message<?= count($ticketMessages) === 1 ? '' : 's' ?></span>
                        </div>
                        <div class="ticket-thread">
                            <?php foreach ($ticketMessages as $message): ?>
                                <div class="ticket-bubble <?= $message['sender_type'] === 'support' ? 'support' : 'user' ?>">
                                    <strong><?= htmlspecialchars((string)($message['sender_type'] === 'support' ? ($message['sender_label'] ?: 'ToolShare Support') : ($message['sender_label'] ?: $selectedTicket['name']))) ?></strong>
                                    <?= nl2br(htmlspecialchars((string)$message['message_text'])) ?>
                                    <small><?= date('M d, Y h:i A', strtotime((string)$message['created_at'])) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ((string)$selectedTicket['status'] === 'resolved'): ?>
                        <div class="ticket-box ticket-action-box">
                            <div class="ticket-action-head">
                                <h3>Ticket Closed</h3>
                            </div>
                            <div class="ticket-closed-note">This ticket has been closed. No further replies can be sent from the operations side.</div>
                        </div>
                    <?php else: ?>
                        <div class="ticket-box ticket-action-box">
                            <div class="ticket-action-head">
                                <h3>Reply as ToolShare Support</h3>
                            </div>
                            <form method="POST" class="admin-grid">
                                <input type="hidden" name="ticket_id" value="<?= (int)$selectedTicket['id'] ?>">
                                <div>
                                    <label style="display:block; margin-bottom:8px; color:#64748b; font-size:12px; text-transform:uppercase; font-weight:700;">Next Status</label>
                                    <select name="status">
                                        <?php foreach (['open', 'resolved'] as $item): ?>
                                            <option value="<?= $item ?>" <?= $selectedTicket['status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(toolshare_support_status_label($item)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:8px; color:#64748b; font-size:12px; text-transform:uppercase; font-weight:700;">Reply Message</label>
                                    <textarea name="reply_message" placeholder="Reply to the customer as ToolShare Support." required></textarea>
                                </div>
                                <div>
                                    <button type="submit" class="admin-btn">Send Reply</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php toolshare_admin_render_layout_end(); ?>
