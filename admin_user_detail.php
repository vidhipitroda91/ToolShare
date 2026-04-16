<?php
session_start();
require 'config/db.php';
require 'includes/admin_layout.php';

toolshare_require_admin();

if (!function_exists('toolshare_admin_badge_class')) {
    function toolshare_admin_badge_class(string $type, string $value): string
    {
        $value = strtolower(trim($value));

        if ($type === 'status') {
            if ($value === 'active') {
                return 'success';
            }
            return $value === 'suspended' ? 'warning' : 'danger';
        }

        if ($type === 'risk') {
            if ($value === 'repeat_offender') {
                return 'danger';
            }
            return $value === 'watch' ? 'warning' : 'success';
        }

        return 'success';
    }

    function toolshare_admin_label(string $value): string
    {
        return ucwords(str_replace('_', ' ', trim($value)));
    }
}

$selectedId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)($_POST['user_id'] ?? 0);
$fromSearch = trim((string)($_GET['from_search'] ?? $_POST['from_search'] ?? ''));

if ($selectedId <= 0) {
    header('Location: admin_users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action !== '') {
        try {
            $message = toolshare_admin_update_user_moderation($pdo, $selectedId, (int)$_SESSION['user_id'], $action, [
                'notes' => trim((string)($_POST['notes'] ?? '')),
                'risk_level' => trim((string)($_POST['risk_level'] ?? '')),
            ]);

            $query = ['msg' => $message, 'user_id' => $selectedId];
            if ($fromSearch !== '') {
                $query['from_search'] = $fromSearch;
            }
            header('Location: admin_user_detail.php?' . http_build_query($query));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$selectedUser = toolshare_admin_fetch_user_profile($pdo, $selectedId);
if (!$selectedUser) {
    header('Location: admin_users.php' . ($fromSearch !== '' ? '?' . http_build_query(['search' => $fromSearch]) : ''));
    exit;
}

$bookingHistory = toolshare_admin_fetch_user_booking_history($pdo, (int)$selectedUser['id']);
$listingHistory = toolshare_admin_fetch_user_listings($pdo, (int)$selectedUser['id']);
$disputeHistory = toolshare_admin_fetch_user_disputes($pdo, (int)$selectedUser['id']);
$reviewHistory = toolshare_admin_fetch_user_reviews($pdo, (int)$selectedUser['id']);
$adminActions = toolshare_admin_fetch_user_admin_actions($pdo, (int)$selectedUser['id']);

$backHref = 'admin_users.php';
if ($fromSearch !== '') {
    $backHref .= '?' . http_build_query(['search' => $fromSearch]);
}

toolshare_admin_render_layout_start($pdo, 'User Detail', 'users', 'Full user profile, moderation tools, dispute history, and repeat-offender tracking.');
?>

<section class="admin-card">
    <style>
        .user-detail-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; }
        .user-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .user-kpis { display: grid; gap: 14px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; }
        .user-kpi { border: 1px solid var(--admin-line); background: #fff; border-radius: 18px; padding: 14px; }
        .user-kpi strong { display: block; font-size: 1.35rem; color: var(--admin-navy); margin-bottom: 4px; }
        .user-kpi span { color: var(--admin-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .user-section { border: 1px solid var(--admin-line); border-radius: 18px; padding: 18px; background: #fff; }
        .user-section h3 { color: var(--admin-navy); font-size: 1rem; margin-bottom: 14px; }
        .user-detail-grid { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .user-detail-grid div { padding: 12px 14px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .user-detail-grid small { display: block; color: var(--admin-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; margin-bottom: 5px; font-weight: 800; }
        .user-actions { display: grid; gap: 12px; }
        .user-action-row { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .user-action-row.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .user-history-list { display: grid; gap: 10px; }
        .user-history-item { padding: 14px; border: 1px solid #e2e8f0; border-radius: 14px; background: #f8fafc; }
        .user-history-item strong { color: var(--admin-navy); }
        .user-history-item small { color: var(--admin-muted); }
        .user-history-meta { margin-top: 6px; color: var(--admin-muted); font-size: 13px; line-height: 1.5; }
        @media (max-width: 1200px) {
            .user-kpis, .user-detail-grid, .user-action-row, .user-action-row.cols-3, .admin-grid.cols-2 { grid-template-columns: 1fr; }
            .user-detail-top { flex-direction: column; }
        }
    </style>

    <div class="user-detail-top">
        <div>
            <a href="<?= htmlspecialchars($backHref) ?>" class="admin-btn secondary" style="margin-bottom:14px;">Back to Users</a>
            <h2 style="color:var(--admin-navy);"><?= htmlspecialchars($selectedUser['full_name']) ?></h2>
            <p style="color:#64748b; margin-top:6px;"><?= htmlspecialchars($selectedUser['email']) ?></p>
            <div class="user-meta">
                <span class="admin-badge-pill"><?= htmlspecialchars(toolshare_admin_label((string)$selectedUser['role_name'])) ?></span>
                <span class="admin-badge-pill <?= toolshare_admin_badge_class('status', (string)$selectedUser['account_status']) ?>"><?= htmlspecialchars(toolshare_admin_label((string)$selectedUser['account_status'])) ?></span>
                <span class="admin-badge-pill <?= toolshare_admin_badge_class('risk', (string)$selectedUser['risk_level']) ?>"><?= htmlspecialchars(toolshare_admin_label((string)$selectedUser['risk_level'])) ?></span>
                <?php if ((int)$selectedUser['owner_verified'] === 1): ?>
                    <span class="admin-badge-pill success">Owner Verified</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="admin-note" style="margin-bottom:18px; border-color:#fecaca; background:#fef2f2; color:#991b1b;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="user-kpis">
        <div class="user-kpi"><strong><?= (int)$selectedUser['renter_booking_count'] ?></strong><span>Renter Bookings</span></div>
        <div class="user-kpi"><strong><?= (int)$selectedUser['owner_booking_count'] ?></strong><span>Owner Bookings</span></div>
        <div class="user-kpi"><strong><?= (int)$selectedUser['listing_count'] ?></strong><span>Listings</span></div>
        <div class="user-kpi"><strong><?= (int)$selectedUser['open_dispute_count'] ?></strong><span>Open Disputes</span></div>
    </div>

    <div class="admin-card" style="padding:0; box-shadow:none; border:none; background:transparent;">
        <div class="user-section" style="margin-bottom:18px;">
            <h3>Profile Snapshot</h3>
            <div class="user-detail-grid">
                <div><small>User ID</small>#<?= (int)$selectedUser['id'] ?></div>
                <div><small>Phone</small><?= htmlspecialchars((string)($selectedUser['phone'] ?: 'Not provided')) ?></div>
                <div><small>Warnings</small><?= (int)$selectedUser['warning_count'] ?></div>
                <div><small>Reviews Written</small><?= (int)$selectedUser['review_count'] ?><?= $selectedUser['average_rating'] !== null ? ' • avg ' . htmlspecialchars((string)$selectedUser['average_rating']) . '/5' : '' ?></div>
                <div><small>Current Status Reason</small><?= htmlspecialchars((string)($selectedUser['status_reason'] ?: 'None')) ?></div>
                <div><small>Owner Verified At</small><?= htmlspecialchars($selectedUser['verified_owner_at'] ? date('M d, Y h:i A', strtotime((string)$selectedUser['verified_owner_at'])) : 'Not verified') ?></div>
            </div>
        </div>

        <div class="user-section" style="margin-bottom:18px;">
            <h3>Moderation Controls</h3>
            <form method="POST" class="user-actions">
                <input type="hidden" name="user_id" value="<?= (int)$selectedUser['id'] ?>">
                <input type="hidden" name="from_search" value="<?= htmlspecialchars($fromSearch) ?>">

                <textarea name="notes" placeholder="Internal notes, warning reason, suspension reason, or reactivation context."></textarea>

                <div class="user-action-row cols-3">
                    <button type="submit" name="action" value="warn" class="admin-btn secondary">Warn User</button>
                    <?php if ($selectedUser['account_status'] !== 'suspended'): ?>
                        <button type="submit" name="action" value="suspend" class="admin-btn">Suspend</button>
                    <?php else: ?>
                        <button type="submit" name="action" value="reactivate" class="admin-btn">Reactivate</button>
                    <?php endif; ?>
                    <?php if ($selectedUser['account_status'] !== 'blocked'): ?>
                        <button type="submit" name="action" value="block" class="admin-btn danger">Block User</button>
                    <?php else: ?>
                        <button type="submit" name="action" value="reactivate" class="admin-btn">Reactivate</button>
                    <?php endif; ?>
                </div>

                <div class="user-action-row">
                    <button type="submit" name="action" value="<?= (int)$selectedUser['owner_verified'] === 1 ? 'unverify_owner' : 'verify_owner' ?>" class="admin-btn secondary">
                        <?= (int)$selectedUser['owner_verified'] === 1 ? 'Remove Owner Verification' : 'Verify Owner Account' ?>
                    </button>
                    <?php if ($selectedUser['account_status'] !== 'active'): ?>
                        <button type="submit" name="action" value="reactivate" class="admin-btn">Force Reactivate</button>
                    <?php else: ?>
                        <a href="admin_disputes.php?<?= htmlspecialchars(http_build_query(['user_id' => (int)$selectedUser['id']])) ?>" class="admin-btn secondary">View User Disputes</a>
                    <?php endif; ?>
                </div>

                <div class="user-action-row">
                    <select name="risk_level">
                        <?php foreach (['normal', 'watch', 'repeat_offender'] as $riskLevel): ?>
                            <option value="<?= $riskLevel ?>" <?= $selectedUser['risk_level'] === $riskLevel ? 'selected' : '' ?>><?= htmlspecialchars(toolshare_admin_label($riskLevel)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="set_risk" class="admin-btn">Update Risk Flag</button>
                </div>
            </form>
        </div>

        <div class="admin-grid cols-2" style="margin-bottom:18px;">
            <div class="user-section">
                <h3>Recent Bookings</h3>
                <div class="user-history-list">
                    <?php if (empty($bookingHistory)): ?>
                        <div class="admin-note">No booking history found.</div>
                    <?php else: ?>
                        <?php foreach ($bookingHistory as $booking): ?>
                            <div class="user-history-item">
                                <strong>#<?= (int)$booking['id'] ?> • <?= htmlspecialchars($booking['tool_title']) ?></strong><br>
                                <small><?= htmlspecialchars(toolshare_admin_label((string)$booking['user_side'])) ?> • <?= htmlspecialchars(toolshare_admin_label((string)$booking['status'])) ?></small>
                                <div class="user-history-meta">
                                    <?= htmlspecialchars($booking['renter_name']) ?> renting from <?= htmlspecialchars($booking['owner_name']) ?><br>
                                    <?= htmlspecialchars(date('M d, Y h:i A', strtotime((string)$booking['pick_up_datetime']))) ?> to <?= htmlspecialchars(date('M d, Y h:i A', strtotime((string)$booking['drop_off_datetime']))) ?><br>
                                    Total: $<?= number_format((float)$booking['total_price'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="user-section">
                <h3>Listings</h3>
                <div class="user-history-list">
                    <?php if (empty($listingHistory)): ?>
                        <div class="admin-note">No listings published by this user.</div>
                    <?php else: ?>
                        <?php foreach ($listingHistory as $listing): ?>
                            <div class="user-history-item">
                                <strong>#<?= (int)$listing['id'] ?> • <?= htmlspecialchars($listing['title']) ?></strong><br>
                                <small><?= htmlspecialchars((string)($listing['category'] ?: 'Uncategorized')) ?><?= !empty($listing['brand']) ? ' • ' . htmlspecialchars((string)$listing['brand']) : '' ?></small>
                                <div class="user-history-meta">
                                    Daily: $<?= number_format((float)$listing['price_daily'], 2) ?>
                                    <?php if ((float)$listing['price_hourly'] > 0): ?> • Hourly: $<?= number_format((float)$listing['price_hourly'], 2) ?><?php endif; ?>
                                    <?php if ((float)$listing['price_weekly'] > 0): ?> • Weekly: $<?= number_format((float)$listing['price_weekly'], 2) ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="admin-grid cols-2" style="margin-bottom:18px;">
            <div class="user-section">
                <div class="admin-card-header" style="margin-bottom:14px;">
                    <h3 style="margin-bottom:0;">Disputes Involving This User</h3>
                    <a href="admin_disputes.php?<?= htmlspecialchars(http_build_query(['user_id' => (int)$selectedUser['id']])) ?>" class="admin-btn secondary">Open Full Queue</a>
                </div>
                <div class="user-history-list">
                    <?php if (empty($disputeHistory)): ?>
                        <div class="admin-note">No disputes on record.</div>
                    <?php else: ?>
                        <?php foreach ($disputeHistory as $dispute): ?>
                            <div class="user-history-item">
                                <strong>#<?= (int)$dispute['id'] ?> • <?= htmlspecialchars($dispute['tool_title']) ?></strong><br>
                                <small><?= htmlspecialchars(toolshare_admin_label((string)$dispute['dispute_relation'])) ?> • <?= htmlspecialchars($dispute['reason']) ?></small>
                                <div class="user-history-meta">
                                    Status: <?= htmlspecialchars(toolshare_admin_label((string)$dispute['status'])) ?>
                                    <?php if (!empty($dispute['admin_decision'])): ?> • Decision: <?= htmlspecialchars(toolshare_admin_label((string)$dispute['admin_decision'])) ?><?php endif; ?><br>
                                    Owner: <?= htmlspecialchars($dispute['owner_name']) ?> • Renter: <?= htmlspecialchars($dispute['renter_name']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="user-section">
                <h3>Reviews Written</h3>
                <div class="user-history-list">
                    <?php if (empty($reviewHistory)): ?>
                        <div class="admin-note">No review history available.</div>
                    <?php else: ?>
                        <?php foreach ($reviewHistory as $review): ?>
                            <div class="user-history-item">
                                <strong><?= htmlspecialchars($review['tool_title']) ?></strong><br>
                                <small><?= (int)$review['rating'] ?>/5 stars<?= !empty($review['booking_id']) ? ' • booking #' . (int)$review['booking_id'] : '' ?></small>
                                <div class="user-history-meta"><?= nl2br(htmlspecialchars((string)($review['comment'] ?: 'No comment left.'))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="user-section">
            <h3>Admin History</h3>
            <div class="user-history-list">
                <?php if (empty($adminActions)): ?>
                    <div class="admin-note">No moderation history recorded yet.</div>
                <?php else: ?>
                    <?php foreach ($adminActions as $action): ?>
                        <?php
                        $metaParts = [];
                        foreach (($action['metadata_array'] ?? []) as $key => $value) {
                            if (is_scalar($value) && $value !== '') {
                                $metaParts[] = toolshare_admin_label((string)$key) . ': ' . toolshare_admin_label((string)$value);
                            }
                        }
                        ?>
                        <div class="user-history-item">
                            <strong><?= htmlspecialchars(toolshare_admin_label((string)$action['action_type'])) ?></strong><br>
                            <small><?= htmlspecialchars(date('M d, Y h:i A', strtotime((string)$action['created_at']))) ?><?= !empty($action['admin_name']) ? ' • by ' . htmlspecialchars((string)$action['admin_name']) : '' ?></small>
                            <?php if (!empty($action['notes'])): ?>
                                <div class="user-history-meta"><?= nl2br(htmlspecialchars((string)$action['notes'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($metaParts)): ?>
                                <div class="user-history-meta"><?= htmlspecialchars(implode(' • ', $metaParts)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
