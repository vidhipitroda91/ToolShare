<?php
session_start();
require 'config/db.php';
require 'includes/returns_bootstrap.php';
require 'includes/receipt_helpers.php';
require 'includes/dispute_queue_helpers.php';
require 'includes/marketplace_mail_helper.php';
require 'includes/admin_layout.php';

toolshare_require_admin();

$formError = '';
$queue = trim((string)($_GET['queue'] ?? $_POST['queue'] ?? 'owner'));
$queue = in_array($queue, ['owner', 'renter'], true) ? $queue : 'owner';
$queueMeta = toolshare_dispute_queue_meta($queue);

$search = trim((string)($_GET['search'] ?? $_POST['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? $_POST['status_filter'] ?? ''));
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
$disputeId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['dispute_id']) ? (int)$_POST['dispute_id'] : 0);
$message = trim((string)($_GET['msg'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim((string)($_POST['status'] ?? ''));
    $decision = trim((string)($_POST['admin_decision'] ?? ''));
    $depositDeducted = isset($_POST['deposit_deducted']) ? round((float)$_POST['deposit_deducted'], 2) : 0.0;
    $adminNotes = trim((string)($_POST['admin_notes'] ?? ''));

    $allowedStatus = ['pending', 'reviewing', 'resolved', 'rejected'];

    if ($disputeId > 0 && in_array($status, $allowedStatus, true)) {
        $dispute = toolshare_admin_fetch_dispute($pdo, $disputeId);
        if ($dispute) {
            if (in_array((string)$dispute['status'], ['resolved', 'rejected'], true)) {
                $formError = 'This dispute is closed and can only be viewed.';
            }

            $disputeQueue = in_array((string)($dispute['initiated_by'] ?? 'owner'), ['owner', 'renter'], true)
                ? (string)$dispute['initiated_by']
                : 'owner';
            $allowedDecision = toolshare_dispute_queue_meta($disputeQueue)['decision_options'];

            if ($formError === '' && !in_array($decision, $allowedDecision, true)) {
                $formError = 'This decision is not available for the selected dispute type.';
            }

            if ($formError === '' && in_array($status, ['resolved', 'rejected'], true) && $adminNotes === '') {
                $formError = 'Resolution notes are required before a dispute can be closed.';
            }

            if ($formError === '') {
                if ($disputeQueue === 'owner') {
                    if ($status === 'rejected') {
                        $decision = 'full_refund';
                    } elseif ($status === 'resolved' && $decision === 'pending') {
                        $decision = 'full_refund';
                    }

                    if ($decision === 'full_refund') {
                        $depositDeducted = 0.0;
                    } elseif ($decision === 'full_forfeit') {
                        $depositDeducted = round((float)$dispute['deposit_held'], 2);
                    } elseif ($decision !== 'partial_deduction') {
                        $depositDeducted = 0.0;
                    }
                } else {
                    if ($status === 'rejected' && $decision === 'pending') {
                        $decision = 'deny';
                    } elseif ($status === 'resolved' && $decision === 'pending') {
                        $decision = 'replacement_or_manual_resolution';
                    }
                    $depositDeducted = 0.0;
                }

                $depositDeducted = max(0.0, min($depositDeducted, (float)$dispute['deposit_held']));
                $resolvedAt = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
                $depositRefundAmount = round((float)$dispute['deposit_held'] - $depositDeducted, 2);
                $depositStatus = 'held';
                $acknowledgedAt = $dispute['acknowledged_at'] ?? null;
                $reviewStartedAt = $dispute['review_started_at'] ?? null;
                $resolvedByAdminId = null;
                $resolutionSummary = null;

                if ($disputeQueue === 'owner') {
                    if ($decision === 'full_refund') {
                        $depositStatus = 'full_refund';
                    } elseif ($decision === 'partial_deduction') {
                        $depositStatus = $depositRefundAmount > 0 ? 'partial_refund' : 'forfeited';
                    } elseif ($decision === 'full_forfeit') {
                        $depositStatus = 'forfeited';
                    }
                }

                if ($acknowledgedAt === null) {
                    $acknowledgedAt = date('Y-m-d H:i:s');
                }
                if (in_array($status, ['reviewing', 'resolved', 'rejected'], true) && $reviewStartedAt === null) {
                    $reviewStartedAt = date('Y-m-d H:i:s');
                }
                if (in_array($status, ['resolved', 'rejected'], true)) {
                    $resolvedByAdminId = (int)($_SESSION['user_id'] ?? 0) ?: null;
                    if ($adminNotes !== '') {
                        $resolutionSummary = $adminNotes;
                    } elseif ($disputeQueue === 'owner') {
                        $resolutionSummary = $status === 'rejected'
                            ? 'Dispute was closed in favor of the renter with full deposit refund.'
                            : 'Dispute was resolved after evidence review.';
                    } else {
                        $resolutionSummary = $status === 'rejected'
                            ? 'Renter claim was denied after operations review.'
                            : 'Renter claim was reviewed and a service outcome was recorded.';
                    }
                }

                $shouldFinalizeBooking = $disputeQueue === 'owner' && in_array($status, ['resolved', 'rejected'], true);

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        UPDATE disputes
                        SET status = ?,
                            admin_decision = ?,
                            deposit_deducted = ?,
                            admin_notes = ?,
                            acknowledged_at = ?,
                            review_started_at = ?,
                            resolved_by_admin_id = ?,
                            resolution_summary = ?,
                            resolved_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $status,
                        $decision,
                        $depositDeducted,
                        $adminNotes !== '' ? $adminNotes : null,
                        $acknowledgedAt,
                        $reviewStartedAt,
                        $resolvedByAdminId,
                        $resolutionSummary,
                        $resolvedAt,
                        $disputeId,
                    ]);

                    if ($shouldFinalizeBooking) {
                        $complete = $pdo->prepare("
                            UPDATE bookings
                            SET status = 'completed',
                                return_reviewed_at = COALESCE(return_reviewed_at, NOW()),
                                deposit_status = ?,
                                deposit_refund_amount = ?
                            WHERE id = ?
                              AND status = 'paid'
                        ");
                        $complete->execute([$depositStatus, $depositRefundAmount, $dispute['booking_id']]);
                    }

                    if (toolshare_table_exists($pdo, 'dispute_history')) {
                        $history = $pdo->prepare("
                            INSERT INTO dispute_history (
                                dispute_id,
                                admin_id,
                                previous_status,
                                new_status,
                                previous_decision,
                                new_decision,
                                deposit_deducted,
                                admin_notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $history->execute([
                            $disputeId,
                            (int)($_SESSION['user_id'] ?? 0) ?: null,
                            $dispute['status'],
                            $status,
                            $dispute['admin_decision'],
                            $decision,
                            $depositDeducted,
                            $adminNotes !== '' ? $adminNotes : null,
                        ]);
                    }

                    $pdo->commit();
                    toolshare_mail_send_dispute_updated_notifications($pdo, $disputeId);
                    if ($shouldFinalizeBooking) {
                        toolshare_sync_booking_charges($pdo, (int)$dispute['booking_id']);
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $redirectQuery = [
                    'id' => $disputeId,
                    'queue' => $disputeQueue,
                    'msg' => 'Dispute updated',
                ];
                if ($search !== '') {
                    $redirectQuery['search'] = $search;
                }
                if ($statusFilter !== '') {
                    $redirectQuery['status'] = $statusFilter;
                }
                if ($userId > 0) {
                    $redirectQuery['user_id'] = $userId;
                }

                header('Location: admin_dispute_case.php?' . http_build_query($redirectQuery));
                exit;
            }
        }
    }
}

$selectedDispute = $disputeId > 0 ? toolshare_admin_fetch_dispute($pdo, $disputeId, ['queue' => $queue]) : null;
$disputeHistory = $selectedDispute ? toolshare_admin_fetch_dispute_history($pdo, (int)$selectedDispute['id']) : [];
if ($selectedDispute) {
    toolshare_mark_notification_item_viewed($pdo, (int)($_SESSION['user_id'] ?? 0), 'admin', 'dispute', (string)(int)$selectedDispute['id']);
}
$backQuery = ['queue' => $queue];
if ($search !== '') {
    $backQuery['search'] = $search;
}
if ($statusFilter !== '') {
    $backQuery['status'] = $statusFilter;
}
if ($userId > 0) {
    $backQuery['user_id'] = $userId;
}

toolshare_admin_render_layout_start($pdo, 'Dispute Case', 'disputes', 'Use a dedicated case workspace for full evidence review, resolution notes, and decision history.');
?>

<style>
    .case-workspace { display: grid; gap: 20px; }
    .case-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .case-topbar .admin-btn.secondary { text-decoration: none; }
    .case-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }
    .case-header h2 {
        font-size: 1.55rem;
        color: var(--admin-navy);
        margin-bottom: 6px;
    }
    .case-header p { color: var(--admin-muted); line-height: 1.65; }
    .case-two-up {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .case-card {
        border: 1px solid var(--admin-line);
        border-radius: 20px;
        padding: 18px;
        background: #fff;
    }
    .case-card h3 {
        margin-bottom: 12px;
        color: var(--admin-navy);
        font-size: 1rem;
    }
    .case-line {
        padding: 8px 0;
        border-bottom: 1px dashed #dbe6ee;
        color: var(--admin-muted);
        line-height: 1.55;
    }
    .case-line:last-child { border-bottom: none; }
    .case-line strong { color: var(--admin-text); }
    .case-note-block {
        padding: 16px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        color: var(--admin-muted);
        line-height: 1.7;
    }
    .case-mode-note {
        margin-top: 12px;
        padding: 12px 14px;
        border-radius: 14px;
        background: #fff7ed;
        border: 1px solid #fdba74;
        color: #9a3412;
        line-height: 1.6;
    }
    .case-evidence {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 12px;
    }
    .case-evidence a { text-decoration: none; }
    .case-evidence img {
        width: 96px;
        height: 96px;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid var(--admin-line);
    }
    .case-history-table td { font-size: 13px; }
    @media (max-width: 900px) {
        .case-two-up { grid-template-columns: 1fr; }
    }
</style>

<div class="case-workspace">
    <div class="case-topbar">
        <a href="admin_disputes.php?<?= htmlspecialchars(http_build_query($backQuery)) ?>" class="admin-btn secondary">Back To Queue</a>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="dispute_decision_guidelines.php" class="admin-btn secondary">View Guidelines</a>
            <a href="dispute_decision_guidelines_pdf.php" class="admin-btn">Download PDF</a>
        </div>
    </div>

    <?php if ($formError !== ''): ?>
        <div class="admin-alert" style="background:#fef2f2; border-color:#fecaca; color:#991b1b;"><?= htmlspecialchars($formError) ?></div>
    <?php elseif ($message !== ''): ?>
        <div class="admin-alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!$selectedDispute): ?>
        <section class="admin-card">
            <div class="admin-note">This dispute could not be found in the selected queue.</div>
        </section>
    <?php else: ?>
        <?php $evidence = toolshare_dispute_evidence_list($selectedDispute['evidence_paths'] ?? null); ?>
        <?php $isLocked = in_array((string)$selectedDispute['status'], ['resolved', 'rejected'], true); ?>
        <section class="admin-card">
            <div class="case-header">
                <div>
                    <span class="admin-badge-pill warning"><?= htmlspecialchars($queueMeta['badge']) ?></span>
                    <h2>Dispute #<?= (int)$selectedDispute['id'] ?></h2>
                    <p><?= htmlspecialchars($queueMeta['panel_subtitle']) ?></p>
                </div>
                <span class="admin-badge-pill <?= in_array($selectedDispute['status'], ['pending', 'reviewing'], true) ? 'warning' : 'success' ?>"><?= htmlspecialchars(ucfirst((string)$selectedDispute['status'])) ?></span>
            </div>
        </section>

        <?php if ($isLocked): ?>
            <section class="case-card">
                <h3>Final Decision</h3>
                <div class="case-note-block">
                    This dispute has been closed. The recorded decision can still be reviewed below, but it can no longer be changed.
                </div>
            </section>
        <?php endif; ?>

        <div class="case-two-up">
            <section class="case-card">
                <h3>Case Overview</h3>
                <div class="case-line"><strong>Booking</strong><br>#<?= (int)$selectedDispute['booking_id'] ?></div>
                <div class="case-line"><strong>Tool</strong><br><?= htmlspecialchars($selectedDispute['tool_title']) ?></div>
                <div class="case-line"><strong><?= htmlspecialchars($queueMeta['primary_party_label']) ?></strong><br><?= htmlspecialchars($queue === 'owner' ? $selectedDispute['owner_name'] : $selectedDispute['renter_name']) ?></div>
                <div class="case-line"><strong><?= htmlspecialchars($queueMeta['secondary_party_label']) ?></strong><br><?= htmlspecialchars($queue === 'owner' ? $selectedDispute['renter_name'] : $selectedDispute['owner_name']) ?></div>
            </section>
            <section class="case-card">
                <h3>Financial Snapshot</h3>
                <div class="case-line"><strong>Rental Amount</strong><br>$<?= number_format((float)$selectedDispute['total_price'], 2) ?></div>
                <div class="case-line"><strong>Deposit Held</strong><br>$<?= number_format((float)$selectedDispute['deposit_held'], 2) ?></div>
                <div class="case-line"><strong>Current Deposit Status</strong><br><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$selectedDispute['deposit_status']))) ?></div>
                <div class="case-line"><strong>Current Refund Amount</strong><br>$<?= number_format((float)$selectedDispute['deposit_refund_amount'], 2) ?></div>
                <div class="case-line"><strong>Rental Window</strong><br><?= date('M d, Y h:i A', strtotime((string)$selectedDispute['pick_up_datetime'])) ?> to <?= date('M d, Y h:i A', strtotime((string)$selectedDispute['drop_off_datetime'])) ?></div>
            </section>
        </div>

        <section class="case-card">
            <h3>Case Timeline</h3>
            <div class="case-line"><strong>Submitted</strong><br><?= date('M d, Y h:i A', strtotime((string)$selectedDispute['created_at'])) ?></div>
            <div class="case-line"><strong>Acknowledged</strong><br><?= !empty($selectedDispute['acknowledged_at']) ? date('M d, Y h:i A', strtotime((string)$selectedDispute['acknowledged_at'])) : 'Not yet acknowledged' ?></div>
            <div class="case-line"><strong>Review Started</strong><br><?= !empty($selectedDispute['review_started_at']) ? date('M d, Y h:i A', strtotime((string)$selectedDispute['review_started_at'])) : 'Not yet started' ?></div>
            <div class="case-line"><strong>Resolved</strong><br><?= !empty($selectedDispute['resolved_at']) ? date('M d, Y h:i A', strtotime((string)$selectedDispute['resolved_at'])) : 'Still open' ?></div>
            <?php if (!empty($selectedDispute['resolution_summary'])): ?>
                <div class="case-line"><strong>Resolution Summary</strong><br><?= nl2br(htmlspecialchars((string)$selectedDispute['resolution_summary'])) ?></div>
            <?php endif; ?>
        </section>

        <section class="case-card">
            <h3>Claim & Evidence</h3>
            <div class="case-note-block">
                <strong style="display:block; color:var(--admin-navy); margin-bottom:8px;">Reason</strong>
                <?= htmlspecialchars($selectedDispute['reason']) ?><br><br>
                <strong style="display:block; color:var(--admin-navy); margin-bottom:8px;">Description</strong>
                <?= nl2br(htmlspecialchars($selectedDispute['description'])) ?>
                <?php if (!empty($selectedDispute['owner_notes'])): ?>
                    <br><br>
                    <strong style="display:block; color:var(--admin-navy); margin-bottom:8px;"><?= $queue === 'renter' ? 'Supporting Notes' : 'Inspection Notes' ?></strong>
                    <?= nl2br(htmlspecialchars((string)$selectedDispute['owner_notes'])) ?>
                <?php endif; ?>
                <?php if (!empty($evidence)): ?>
                    <div class="case-evidence">
                        <?php foreach ($evidence as $path): ?>
                            <a href="<?= htmlspecialchars($path) ?>" target="_blank" rel="noreferrer"><img src="<?= htmlspecialchars($path) ?>" alt="Dispute evidence"></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="case-card">
            <h3><?= htmlspecialchars($queueMeta['support_title']) ?></h3>
            <div class="case-note-block">
                <strong style="display:block; color:var(--admin-navy); margin-bottom:8px;">Use these rules</strong>
                <?= nl2br(htmlspecialchars($queueMeta['support_copy'])) ?>
            </div>
        </section>

        <section class="case-card">
            <h3><?= $isLocked ? 'Recorded Decision' : 'Resolve This Case' ?></h3>
            <?php if ($isLocked): ?>
                <div class="case-line"><strong>Dispute Status</strong><br><?= htmlspecialchars(ucfirst((string)$selectedDispute['status'])) ?></div>
                <div class="case-line"><strong>Admin Decision</strong><br><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$selectedDispute['admin_decision']))) ?></div>
                <?php if ($queue === 'owner'): ?>
                    <div class="case-line"><strong>Deposit Deducted</strong><br>$<?= number_format((float)$selectedDispute['deposit_deducted'], 2) ?></div>
                    <div class="case-line"><strong>Refund To Renter</strong><br>$<?= number_format(max(0, (float)$selectedDispute['deposit_held'] - (float)$selectedDispute['deposit_deducted']), 2) ?></div>
                <?php else: ?>
                    <div class="case-mode-note">
                        This renter dispute has already been closed with the recorded service outcome above.
                    </div>
                <?php endif; ?>
                <div class="case-line"><strong>Resolution Notes</strong><br><?= nl2br(htmlspecialchars((string)($selectedDispute['admin_notes'] ?? 'No resolution notes recorded.'))) ?></div>
            <?php else: ?>
                <form method="POST" class="admin-grid">
                    <input type="hidden" name="dispute_id" value="<?= (int)$selectedDispute['id'] ?>">
                    <input type="hidden" name="queue" value="<?= htmlspecialchars($queue) ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">
                    <input type="hidden" name="user_id" value="<?= $userId > 0 ? $userId : '' ?>">
                    <div class="admin-filter-row cols-2" style="margin-bottom:0;">
                        <div>
                            <label style="display:block; margin-bottom:8px; color:#64748b; font-size:12px; text-transform:uppercase; font-weight:700;">Dispute Status</label>
                            <select name="status">
                                <?php foreach (['pending', 'reviewing', 'resolved', 'rejected'] as $item): ?>
                                    <option value="<?= $item ?>" <?= $selectedDispute['status'] === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:8px; color:#64748b; font-size:12px; text-transform:uppercase; font-weight:700;">Admin Decision</label>
                            <select name="admin_decision">
                                <?php foreach ($queueMeta['decision_options'] as $item): ?>
                                    <option value="<?= $item ?>" <?= $selectedDispute['admin_decision'] === $item ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $item)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($queue === 'owner'): ?>
                        <div>
                            <label style="display:block; margin-bottom:8px; color:#64748b; font-size:12px; text-transform:uppercase; font-weight:700;">Deposit Deducted</label>
                            <input type="number" step="0.01" min="0" max="<?= htmlspecialchars((string)$selectedDispute['deposit_held']) ?>" name="deposit_deducted" value="<?= htmlspecialchars((string)$selectedDispute['deposit_deducted']) ?>">
                            <div class="admin-note" style="margin-top:10px;">
                                Refund to renter: $<?= number_format(max(0, (float)$selectedDispute['deposit_held'] - (float)$selectedDispute['deposit_deducted']), 2) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="case-mode-note">
                            This renter queue now has its own decision track. Refund amount, deposit refund, and owner payout controls will be added with the renter-claim workflow, but operations can already triage, review, and record the correct renter-facing outcome here.
                        </div>
                    <?php endif; ?>
                    <div>
                        <label style="display:block; margin-bottom:8px; color:#64748b; font-size:12px; text-transform:uppercase; font-weight:700;">Resolution Notes</label>
                        <textarea name="admin_notes" placeholder="Required when closing the dispute. Summarize the evidence reviewed, the conclusion reached, and why the selected refund or deduction is fair."><?= htmlspecialchars((string)($selectedDispute['admin_notes'] ?? '')) ?></textarea>
                        <div class="admin-note" style="margin-top:10px;">
                            Required when status is set to <strong>Resolved</strong> or <strong>Rejected</strong>.
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="admin-btn">Save Decision</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="case-card">
            <div class="admin-card-header" style="margin-bottom:12px;">
                <h3 style="margin:0;">Resolution History</h3>
            </div>
            <?php if (empty($disputeHistory)): ?>
                <div class="admin-note">No history entries yet for this dispute.</div>
            <?php else: ?>
                <div class="admin-table-wrap case-history-table">
                    <table>
                        <thead>
                            <tr><th>When</th><th>Admin</th><th>Status</th><th>Decision</th><th>Deduction</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputeHistory as $entry): ?>
                                <tr>
                                    <td><?= date('M d, Y h:i A', strtotime((string)$entry['created_at'])) ?></td>
                                    <td><?= htmlspecialchars((string)($entry['admin_name'] ?: 'Admin #' . (int)$entry['admin_id'])) ?></td>
                                    <td><?= htmlspecialchars(ucfirst((string)($entry['previous_status'] ?: 'new'))) ?> -> <?= htmlspecialchars(ucfirst((string)$entry['new_status'])) ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$entry['new_decision']))) ?></td>
                                    <td>$<?= number_format((float)$entry['deposit_deducted'], 2) ?></td>
                                    <td><?= nl2br(htmlspecialchars((string)($entry['admin_notes'] ?? ''))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php toolshare_admin_render_layout_end(); ?>
