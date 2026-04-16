<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

if (!function_exists('toolshare_booking_workspace_badge')) {
    function toolshare_booking_workspace_badge(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['completed', 'paid', 'released', 'resolved'], true)) {
            return 'success';
        }
        if (in_array($value, ['pending', 'reviewing', 'return requested', 'awaiting review', 'partial deduction'], true)) {
            return 'warning';
        }
        if (in_array($value, ['cancelled', 'under dispute', 'forfeited'], true)) {
            return 'danger';
        }
        return '';
    }
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$tab = trim($_GET['tab'] ?? 'all');
$allowedTabs = ['all', 'active', 'returns', 'disputes', 'settlements'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'all';
}

$allBookings = toolshare_admin_fetch_bookings($pdo, ['search' => '', 'status' => '']);
$bookings = toolshare_admin_fetch_bookings($pdo, ['search' => $search, 'status' => $status]);

$summary = [
    'active' => 0,
    'returns' => 0,
    'disputes' => 0,
    'settlements' => 0,
];

foreach ($allBookings as $booking) {
    $hasOpenDispute = in_array((string)$booking['dispute_status'], ['pending', 'reviewing'], true);
    $isReturnAwaiting = !empty($booking['returned_at']) && empty($booking['return_reviewed_at']) && !$hasOpenDispute;
    $isPendingSettlement = (string)$booking['dispute_status'] !== 'none' && (string)$booking['settlement_status'] === 'pending';

    if ((string)$booking['status'] === 'paid' && empty($booking['returned_at'])) {
        $summary['active']++;
    }
    if ($isReturnAwaiting) {
        $summary['returns']++;
    }
    if ($hasOpenDispute) {
        $summary['disputes']++;
    }
    if ($isPendingSettlement) {
        $summary['settlements']++;
    }
}

$bookings = array_values(array_filter($bookings, static function (array $booking) use ($tab): bool {
    $hasOpenDispute = in_array((string)$booking['dispute_status'], ['pending', 'reviewing'], true);
    $isReturnAwaiting = !empty($booking['returned_at']) && empty($booking['return_reviewed_at']) && !$hasOpenDispute;
    $isPendingSettlement = (string)$booking['dispute_status'] !== 'none' && (string)$booking['settlement_status'] === 'pending';

    switch ($tab) {
        case 'active':
            return (string)$booking['status'] === 'paid' && empty($booking['returned_at']);
        case 'returns':
            return $isReturnAwaiting;
        case 'disputes':
            return $hasOpenDispute;
        case 'settlements':
            return $isPendingSettlement;
        default:
            return true;
    }
}));

toolshare_admin_render_layout_start($pdo, 'Booking Operations', 'bookings', 'Monitor active bookings, return reviews, escalations, and settlement actions from one operations workspace.');
?>

<style>
    .ops-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.7fr);
        gap: 18px;
        align-items: stretch;
    }
    .ops-hero-copy h2 {
        font-size: 1.9rem;
        color: var(--admin-navy);
        letter-spacing: -0.04em;
        margin-bottom: 10px;
    }
    .ops-hero-copy p {
        color: var(--admin-muted);
        line-height: 1.7;
        max-width: 62ch;
    }
    .ops-hero-note {
        display: grid;
        gap: 12px;
    }
    .ops-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }
    .ops-kpi {
        border: 1px solid var(--admin-line);
        border-radius: 20px;
        padding: 18px;
        background: linear-gradient(180deg, rgba(255,255,255,0.94), rgba(248,250,252,0.9));
    }
    .ops-kpi strong {
        display: block;
        color: var(--admin-muted);
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 10px;
    }
    .ops-kpi span {
        display: block;
        font-size: 2rem;
        font-weight: 900;
        color: var(--admin-navy);
        margin-bottom: 6px;
    }
    .ops-kpi small { color: var(--admin-muted); line-height: 1.55; }
    .ops-toolbar {
        display: grid;
        gap: 16px;
    }
    .ops-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .ops-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 800;
        border: 1px solid var(--admin-line);
        color: var(--admin-navy);
        background: #fff;
    }
    .ops-tab.active {
        background: var(--admin-teal);
        color: #fff;
        border-color: var(--admin-teal);
    }
    .ops-table table { width: 100%; }
    .ops-table thead th {
        font-size: 0.74rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .ops-case-title {
        font-size: 1.2rem;
        color: var(--admin-navy);
        margin-bottom: 4px;
    }
    .ops-case-sub,
    .ops-meta-stack {
        color: var(--admin-muted);
        line-height: 1.55;
        font-size: 0.92rem;
    }
    .ops-amount {
        font-size: 1.2rem;
        font-weight: 900;
        color: var(--admin-navy);
        margin-bottom: 6px;
    }
    .ops-status-stack {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ops-action-cell {
        display: grid;
        gap: 10px;
        justify-items: start;
    }
    .ops-inline-note {
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: var(--admin-muted);
        line-height: 1.6;
    }
    @media (max-width: 1200px) {
        .ops-hero,
        .ops-kpis {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 860px) {
        .ops-hero,
        .ops-kpis {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="admin-card">
    <div class="ops-hero">
        <div class="ops-hero-copy">
            <span class="admin-badge-pill">Booking Workspace</span>
            <h2>Booking Operations</h2>
            <p>
                Track rental activity like a case-management workspace: what is in progress, what needs return review,
                what is escalated, and which bookings still need a final settlement decision. The page keeps summary first
                and full details on demand.
            </p>
        </div>
        <div class="ops-hero-note">
            <div class="ops-inline-note">
                <strong style="display:block; color:var(--admin-navy); margin-bottom:8px;">Priority Rules</strong>
                Review returns first, then open disputes, then any bookings still waiting on a settlement outcome.
            </div>
            <div class="ops-inline-note">
                <strong style="display:block; color:var(--admin-navy); margin-bottom:8px;">Primary Goal</strong>
                Help operations staff understand what needs action next instead of forcing them to scan every raw field.
            </div>
        </div>
    </div>
</section>

<section class="ops-kpis">
    <div class="ops-kpi">
        <strong>Active Bookings</strong>
        <span><?= number_format($summary['active']) ?></span>
        <small>Currently in rental or still running with no return submitted yet.</small>
    </div>
    <div class="ops-kpi">
        <strong>Return Requests</strong>
        <span><?= number_format($summary['returns']) ?></span>
        <small>Returned bookings still waiting for owner or operations review.</small>
    </div>
    <div class="ops-kpi">
        <strong>Open Disputes</strong>
        <span><?= number_format($summary['disputes']) ?></span>
        <small>Escalated cases that are pending or actively under review.</small>
    </div>
    <div class="ops-kpi">
        <strong>Pending Settlements</strong>
        <span><?= number_format($summary['settlements']) ?></span>
        <small>Cases where the financial outcome still needs to be finalized.</small>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Operations Workspace</h2>
        <span class="admin-badge-pill"><?= count($bookings) ?> records in current view</span>
    </div>

    <div class="ops-toolbar">
        <div class="ops-tabs">
            <?php foreach ($allowedTabs as $tabKey): ?>
                <?php
                $labelMap = [
                    'all' => 'All',
                    'active' => 'Active',
                    'returns' => 'Returns',
                    'disputes' => 'Disputes',
                    'settlements' => 'Settlements',
                ];
                $tabQuery = ['tab' => $tabKey];
                if ($search !== '') {
                    $tabQuery['search'] = $search;
                }
                if ($status !== '') {
                    $tabQuery['status'] = $status;
                }
                ?>
                <a href="admin_bookings.php?<?= htmlspecialchars(http_build_query($tabQuery)) ?>" class="ops-tab <?= $tab === $tabKey ? 'active' : '' ?>">
                    <?= htmlspecialchars($labelMap[$tabKey]) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="admin-filter-row">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Booking ID, tool, renter, owner">
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'confirmed', 'paid', 'completed', 'cancelled'] as $item): ?>
                    <option value="<?= $item ?>" <?= $status === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="admin_bookings.php" class="admin-btn secondary">Reset</a>
            <button type="submit" class="admin-btn">Apply Filters</button>
        </form>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="admin-note" style="margin-top:18px;">No bookings matched the current workspace filters.</div>
    <?php else: ?>
        <div class="admin-table-wrap ops-table" style="margin-top:18px;">
            <table>
                <thead>
                    <tr>
                        <th>Booking Case</th>
                        <th>Schedule & Amount</th>
                        <th>Status Overview</th>
                        <th>Primary Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        $hasOpenDispute = in_array((string)$booking['dispute_status'], ['pending', 'reviewing'], true);
                        $hasResolvedDispute = in_array((string)$booking['dispute_status'], ['resolved', 'rejected'], true);
                        $returnStatus = 'In Rental';
                        if ((string)$booking['status'] === 'completed') {
                            $returnStatus = 'Completed';
                        } elseif ($hasOpenDispute) {
                            $returnStatus = 'Under Dispute';
                        } elseif (!empty($booking['returned_at']) && empty($booking['return_reviewed_at'])) {
                            $returnStatus = 'Awaiting Review';
                        } elseif (!empty($booking['returned_at'])) {
                            $returnStatus = 'Reviewed';
                        }

                        $settlementLabel = (string)$booking['settlement_status'];
                        if ($settlementLabel === 'pending' && $booking['dispute_status'] === 'none') {
                            $settlementLabel = 'No Settlement';
                        } else {
                            $settlementLabel = ucwords(str_replace('_', ' ', $settlementLabel));
                        }

                        $actionHref = 'admin_bookings.php?search=' . urlencode((string)$booking['id']);
                        $actionLabel = 'View Booking';
                        if ($hasOpenDispute) {
                            $actionHref = 'admin_disputes.php?search=' . urlencode((string)$booking['id']);
                            $actionLabel = 'Resolve Dispute';
                        } elseif (!empty($booking['returned_at']) && empty($booking['return_reviewed_at'])) {
                            $actionLabel = 'Review Return';
                        } elseif ((string)$booking['status'] === 'paid') {
                            $actionLabel = 'Monitor Booking';
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="ops-case-title">#<?= (int)$booking['id'] ?> · <?= htmlspecialchars($booking['tool_title']) ?></div>
                                <div class="ops-case-sub">Renter: <?= htmlspecialchars($booking['renter_name']) ?></div>
                                <div class="ops-case-sub">Owner: <?= htmlspecialchars($booking['owner_name']) ?></div>
                                <div class="ops-case-sub" style="margin-top:8px;">Pricing: <?= htmlspecialchars((string)$booking['duration_type']) ?> x <?= (int)$booking['duration_count'] ?></div>
                            </td>
                            <td>
                                <div class="ops-amount">$<?= number_format((float)$booking['total_price'], 2) ?></div>
                                <div class="ops-meta-stack">Deposit: $<?= number_format((float)$booking['security_deposit'], 2) ?></div>
                                <div class="ops-meta-stack" style="margin-top:8px;">
                                    <?= date('M d, Y h:i A', strtotime($booking['pick_up_datetime'])) ?><br>
                                    to <?= date('M d, Y h:i A', strtotime($booking['drop_off_datetime'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="ops-status-stack">
                                    <span class="admin-badge-pill <?= toolshare_booking_workspace_badge((string)$booking['status']) ?>">
                                        Payment: <?= htmlspecialchars(ucfirst((string)$booking['status'])) ?>
                                    </span>
                                    <span class="admin-badge-pill <?= toolshare_booking_workspace_badge($returnStatus) ?>">
                                        Return: <?= htmlspecialchars($returnStatus) ?>
                                    </span>
                                    <span class="admin-badge-pill <?= $booking['dispute_status'] === 'none' ? 'success' : toolshare_booking_workspace_badge((string)$booking['dispute_status']) ?>">
                                        Dispute: <?= htmlspecialchars($booking['dispute_status'] === 'none' ? 'None' : ucfirst((string)$booking['dispute_status'])) ?>
                                    </span>
                                    <span class="admin-badge-pill <?= toolshare_booking_workspace_badge($settlementLabel) ?>">
                                        Settlement: <?= htmlspecialchars($settlementLabel) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="ops-action-cell">
                                    <a href="<?= htmlspecialchars($actionHref) ?>" class="admin-btn"><?= htmlspecialchars($actionLabel) ?></a>
                                    <span class="ops-case-sub">
                                        <?php if ($hasOpenDispute): ?>
                                            Escalated case requiring operations review.
                                        <?php elseif (!empty($booking['returned_at']) && empty($booking['return_reviewed_at'])): ?>
                                            Return has been submitted and needs review.
                                        <?php elseif ((string)$booking['status'] === 'paid'): ?>
                                            Rental is active or upcoming and should be monitored.
                                        <?php else: ?>
                                            Open the case if staff needs more booking detail.
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php toolshare_admin_render_layout_end(); ?>
