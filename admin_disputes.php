<?php
session_start();
require 'config/db.php';
require 'includes/dispute_queue_helpers.php';
require 'includes/admin_layout.php';

toolshare_require_admin();

$queue = trim((string)($_GET['queue'] ?? 'owner'));
$queue = in_array($queue, ['owner', 'renter'], true) ? $queue : 'owner';
$queueMeta = toolshare_dispute_queue_meta($queue);

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$message = trim((string)($_GET['msg'] ?? ''));

$allDisputes = toolshare_admin_fetch_disputes($pdo, ['search' => '', 'status' => '', 'user_id' => 0, 'queue' => $queue]);
$disputes = toolshare_admin_fetch_disputes($pdo, ['search' => $search, 'status' => $status, 'user_id' => $userId, 'queue' => $queue]);

$disputeSummary = [
    'open' => 0,
    'reviewing' => 0,
    'resolved' => 0,
    'high_value' => 0,
];
foreach ($allDisputes as $summaryRow) {
    $statusValue = (string)$summaryRow['status'];
    if ($statusValue === 'pending') {
        $disputeSummary['open']++;
    }
    if ($statusValue === 'reviewing') {
        $disputeSummary['reviewing']++;
    }
    if (in_array($statusValue, ['resolved', 'rejected'], true)) {
        $disputeSummary['resolved']++;
    }
    if (($queue === 'owner' && (float)$summaryRow['deposit_held'] >= 100) || ($queue === 'renter' && in_array((string)$summaryRow['status'], ['pending', 'reviewing'], true))) {
        $disputeSummary['high_value']++;
    }
}

toolshare_admin_render_layout_start($pdo, 'Disputes', 'disputes', 'Review dispute queues, then open a dedicated case page for full evidence and resolution work.');
?>

<style>
    .dispute-workspace { display: grid; gap: 22px; }
    .dispute-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.7fr);
        gap: 18px;
        align-items: stretch;
    }
    .dispute-hero-copy h2 {
        font-size: 1.85rem;
        color: var(--admin-navy);
        letter-spacing: -0.04em;
        margin-bottom: 10px;
    }
    .dispute-hero-copy p {
        color: var(--admin-muted);
        line-height: 1.7;
        max-width: 62ch;
    }
    .dispute-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }
    .dispute-kpi {
        border: 1px solid var(--admin-line);
        border-radius: 20px;
        padding: 18px;
        background: linear-gradient(180deg, rgba(255,255,255,0.95), rgba(248,250,252,0.88));
    }
    .dispute-kpi strong {
        display: block;
        color: var(--admin-muted);
        font-size: 0.76rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .dispute-kpi span {
        display: block;
        font-size: 2rem;
        font-weight: 900;
        color: var(--admin-navy);
        margin-bottom: 6px;
    }
    .dispute-kpi small { color: var(--admin-muted); line-height: 1.55; }
    .dispute-toolbar { display: grid; gap: 16px; }
    .dispute-queue-tabs {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 6px;
        border-radius: 999px;
        background: #e8eef5;
    }
    .dispute-queue-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 170px;
        padding: 12px 18px;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 800;
        color: var(--admin-navy);
        background: transparent;
        transition: 0.2s ease;
    }
    .dispute-queue-tab:hover,
    .dispute-queue-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #1f7a86, #2d8b98);
        box-shadow: 0 10px 24px rgba(31, 122, 134, 0.22);
    }
    .dispute-list-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .dispute-list-card {
        display: grid;
        gap: 12px;
        padding: 18px;
        border: 1px solid var(--admin-line);
        border-radius: 20px;
        background: #fff;
    }
    .dispute-list-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .dispute-case-id {
        font-size: 1.2rem;
        font-weight: 900;
        color: var(--admin-navy);
    }
    .dispute-list-title {
        font-weight: 900;
        color: var(--admin-text);
        font-size: 1.08rem;
    }
    .dispute-list-meta {
        color: var(--admin-muted);
        font-size: 0.94rem;
        line-height: 1.58;
    }
    .dispute-list-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .dispute-summary-pill {
        padding: 12px 14px;
        border-radius: 16px;
        border: 1px solid var(--admin-line);
        background: #f8fafc;
    }
    .dispute-summary-pill strong {
        display: block;
        margin-bottom: 5px;
        color: var(--admin-muted);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .dispute-summary-pill span {
        display: block;
        color: var(--admin-navy);
        font-weight: 800;
    }
    .dispute-list-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 4px;
    }
    .dispute-list-actions p {
        margin: 0;
        color: var(--admin-muted);
        font-size: 0.9rem;
    }
    @media (max-width: 1200px) {
        .dispute-hero,
        .dispute-kpis,
        .dispute-list-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 760px) {
        .dispute-list-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dispute-workspace">
    <section class="admin-card">
        <div class="dispute-hero">
            <div class="dispute-hero-copy">
                <span class="admin-badge-pill warning"><?= htmlspecialchars($queueMeta['badge']) ?></span>
                <h2>Dispute Operations</h2>
                <p><?= htmlspecialchars($queueMeta['intro']) ?></p>
            </div>
            <div class="admin-note">
                <strong style="display:block; color:var(--admin-navy); margin-bottom:8px;">Reviewer Focus</strong>
                <?= htmlspecialchars($queueMeta['focus']) ?>
            </div>
        </div>
    </section>

    <section class="dispute-kpis">
        <div class="dispute-kpi">
            <strong><?= htmlspecialchars($queueMeta['pending_label']) ?></strong>
            <span><?= number_format($disputeSummary['open']) ?></span>
            <small><?= htmlspecialchars($queueMeta['pending_note']) ?></small>
        </div>
        <div class="dispute-kpi">
            <strong><?= htmlspecialchars($queueMeta['reviewing_label']) ?></strong>
            <span><?= number_format($disputeSummary['reviewing']) ?></span>
            <small><?= htmlspecialchars($queueMeta['reviewing_note']) ?></small>
        </div>
        <div class="dispute-kpi">
            <strong><?= htmlspecialchars($queueMeta['closed_label']) ?></strong>
            <span><?= number_format($disputeSummary['resolved']) ?></span>
            <small><?= htmlspecialchars($queueMeta['closed_note']) ?></small>
        </div>
        <div class="dispute-kpi">
            <strong><?= htmlspecialchars($queueMeta['risk_label']) ?></strong>
            <span><?= number_format($disputeSummary['high_value']) ?></span>
            <small><?= htmlspecialchars($queueMeta['risk_note']) ?></small>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card-header">
            <h2><?= htmlspecialchars($queueMeta['queue_title']) ?></h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="dispute_decision_guidelines.php" class="admin-btn secondary">View Guidelines</a>
                <a href="dispute_decision_guidelines_pdf.php" class="admin-btn">Download PDF</a>
            </div>
        </div>
        <?php if ($message !== ''): ?>
            <div class="admin-alert" style="margin-bottom:18px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($userId > 0): ?>
            <div class="admin-note" style="margin-bottom:18px;">
                Showing <?= htmlspecialchars(strtolower($queueMeta['title'])) ?> involving user #<?= $userId ?>.
                <a href="admin_disputes.php?<?= htmlspecialchars(http_build_query(['queue' => $queue])) ?>" style="margin-left:8px; color:#1f7a86; font-weight:800; text-decoration:none;">Clear filter</a>
            </div>
        <?php endif; ?>

        <div class="dispute-toolbar">
            <div class="dispute-queue-tabs" role="tablist" aria-label="Dispute queue type">
                <?php foreach (['owner' => 'Owner Disputes', 'renter' => 'Renter Disputes'] as $queueKey => $queueLabel): ?>
                    <?php
                    $queueQuery = ['queue' => $queueKey];
                    if ($search !== '') {
                        $queueQuery['search'] = $search;
                    }
                    if ($status !== '') {
                        $queueQuery['status'] = $status;
                    }
                    if ($userId > 0) {
                        $queueQuery['user_id'] = $userId;
                    }
                    ?>
                    <a
                        href="admin_disputes.php?<?= htmlspecialchars(http_build_query($queueQuery)) ?>"
                        class="dispute-queue-tab <?= $queue === $queueKey ? 'active' : '' ?>"
                        role="tab"
                        aria-selected="<?= $queue === $queueKey ? 'true' : 'false' ?>"
                    ><?= htmlspecialchars($queueLabel) ?></a>
                <?php endforeach; ?>
            </div>

            <form method="GET" class="admin-filter-row">
                <input type="hidden" name="queue" value="<?= htmlspecialchars($queue) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Dispute ID, booking ID, owner, renter, tool, reason">
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending', 'reviewing', 'resolved', 'rejected'] as $item): ?>
                        <option value="<?= $item ?>" <?= $status === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="user_id" value="<?= $userId > 0 ? $userId : '' ?>">
                <a href="admin_disputes.php?<?= htmlspecialchars(http_build_query(['queue' => $queue])) ?>" class="admin-btn secondary">Reset</a>
                <button type="submit" class="admin-btn">Filter</button>
            </form>

            <?php if (empty($disputes)): ?>
                <div class="admin-note"><?= htmlspecialchars($queueMeta['status_empty']) ?></div>
            <?php else: ?>
                <div class="dispute-list-grid">
                    <?php foreach ($disputes as $dispute): ?>
                        <?php
                        $caseQuery = [
                            'id' => (int)$dispute['id'],
                            'queue' => $queue,
                        ];
                        if ($search !== '') {
                            $caseQuery['search'] = $search;
                        }
                        if ($status !== '') {
                            $caseQuery['status'] = $status;
                        }
                        if ($userId > 0) {
                            $caseQuery['user_id'] = $userId;
                        }
                        ?>
                        <article class="dispute-list-card">
                            <div class="dispute-list-head">
                                <div class="dispute-case-id">Dispute #<?= (int)$dispute['id'] ?></div>
                                <span class="admin-badge-pill <?= in_array($dispute['status'], ['pending', 'reviewing'], true) ? 'warning' : 'success' ?>"><?= htmlspecialchars(ucfirst((string)$dispute['status'])) ?></span>
                            </div>
                            <div class="dispute-list-title"><?= htmlspecialchars($dispute['tool_title']) ?></div>
                            <div class="dispute-list-meta">Reason: <?= htmlspecialchars($dispute['reason']) ?></div>
                            <div class="dispute-list-meta">Booking #<?= (int)$dispute['booking_id'] ?> · <?= htmlspecialchars($queueMeta['primary_party_label']) ?> <?= htmlspecialchars($queue === 'owner' ? $dispute['owner_name'] : $dispute['renter_name']) ?></div>
                            <div class="dispute-list-meta"><?= htmlspecialchars($queueMeta['secondary_party_label']) ?> <?= htmlspecialchars($queue === 'owner' ? $dispute['renter_name'] : $dispute['owner_name']) ?></div>
                            <div class="dispute-list-summary">
                                <div class="dispute-summary-pill">
                                    <strong><?= htmlspecialchars($queueMeta['amount_label']) ?></strong>
                                    <span>$<?= number_format((float)($queue === 'owner' ? $dispute['deposit_held'] : $dispute['total_price']), 2) ?></span>
                                </div>
                                <div class="dispute-summary-pill">
                                    <strong>Raised</strong>
                                    <span><?= date('M d, Y h:i A', strtotime((string)$dispute['created_at'])) ?></span>
                                </div>
                            </div>
                            <div class="dispute-list-actions">
                                <p>Open the dedicated case page for evidence, timeline, and decision history.</p>
                                <a href="admin_dispute_case.php?<?= htmlspecialchars(http_build_query($caseQuery)) ?>" class="admin-btn">Open Case</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php toolshare_admin_render_layout_end(); ?>
