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

$search = trim($_GET['search'] ?? '');
$users = toolshare_admin_fetch_users($pdo, $search);

toolshare_admin_render_layout_start($pdo, 'Users', 'users', 'Browse users and open a dedicated detail page for moderation, profile, and history.');
?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>User Directory</h2>
    </div>

    <form method="GET" class="admin-filter-row cols-2">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by ID, name, or email">
        <button type="submit" class="admin-btn">Search Users</button>
    </form>

    <div class="admin-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Activity</th>
                    <th>Moderation</th>
                    <th>Open</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5"><div class="admin-note">No users matched this search.</div></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $query = ['user_id' => (int)$user['id']];
                        if ($search !== '') {
                            $query['from_search'] = $search;
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                <small style="color:#64748b;">#<?= (int)$user['id'] ?> • <?= htmlspecialchars($user['email']) ?></small>
                            </td>
                            <td>
                                <span class="admin-badge-pill"><?= htmlspecialchars(toolshare_admin_label((string)$user['role_name'])) ?></span>
                                <?php if ((int)$user['owner_verified'] === 1): ?>
                                    <div style="margin-top:8px;"><span class="admin-badge-pill success">Owner Verified</span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= (int)$user['booking_count'] ?></strong> bookings<br>
                                <small style="color:#64748b;"><?= (int)$user['tool_count'] ?> listings • <?= (int)$user['dispute_count'] ?> disputes</small>
                            </td>
                            <td>
                                <span class="admin-badge-pill <?= toolshare_admin_badge_class('status', (string)$user['account_status']) ?>"><?= htmlspecialchars(toolshare_admin_label((string)$user['account_status'])) ?></span>
                                <div style="margin-top:8px;">
                                    <span class="admin-badge-pill <?= toolshare_admin_badge_class('risk', (string)$user['risk_level']) ?>"><?= htmlspecialchars(toolshare_admin_label((string)$user['risk_level'])) ?></span>
                                </div>
                                <?php if ((int)$user['warning_count'] > 0): ?>
                                    <div style="margin-top:8px; color:#64748b; font-size:12px; font-weight:700;"><?= (int)$user['warning_count'] ?> warning(s)</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="admin_user_detail.php?<?= htmlspecialchars(http_build_query($query)) ?>" class="admin-btn">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php toolshare_admin_render_layout_end(); ?>
