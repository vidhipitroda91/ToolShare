<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/user_moderation_bootstrap.php';
require_once __DIR__ . '/dispute_history_bootstrap.php';

if (!function_exists('toolshare_column_exists')) {
    function toolshare_column_exists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
            $cache[$key] = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    function toolshare_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            $cache[$table] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    function toolshare_admin_user_role_expr(PDO $pdo, string $alias = 'u'): string
    {
        return toolshare_column_exists($pdo, 'users', 'role')
            ? "COALESCE($alias.role, 'user')"
            : "'user'";
    }

    function toolshare_admin_user_status_expr(PDO $pdo, string $alias = 'u'): string
    {
        return toolshare_column_exists($pdo, 'users', 'account_status')
            ? "COALESCE($alias.account_status, 'active')"
            : "'active'";
    }

    function toolshare_admin_user_risk_expr(PDO $pdo, string $alias = 'u'): string
    {
        return toolshare_column_exists($pdo, 'users', 'risk_level')
            ? "COALESCE($alias.risk_level, 'normal')"
            : "'normal'";
    }

    function toolshare_admin_user_verified_expr(PDO $pdo, string $alias = 'u'): string
    {
        return toolshare_column_exists($pdo, 'users', 'owner_verified')
            ? "COALESCE($alias.owner_verified, 0)"
            : '0';
    }

    function toolshare_admin_user_warning_expr(PDO $pdo, string $alias = 'u'): string
    {
        return toolshare_column_exists($pdo, 'users', 'warning_count')
            ? "COALESCE($alias.warning_count, 0)"
            : '0';
    }

    function toolshare_admin_nullable_user_column(PDO $pdo, string $column, string $alias = 'u'): string
    {
        return toolshare_column_exists($pdo, 'users', $column) ? "$alias.$column" : 'NULL';
    }

    function toolshare_admin_reviews_available(PDO $pdo): bool
    {
        return toolshare_table_exists($pdo, 'reviews');
    }

    function toolshare_admin_overview(PDO $pdo): array
    {
        $roleExpr = toolshare_admin_user_role_expr($pdo, 'u');

        $overview = [
            'total_users' => (int)$pdo->query("SELECT COUNT(*) FROM users u WHERE {$roleExpr} = 'user'")->fetchColumn(),
            'total_listings' => (int)$pdo->query("SELECT COUNT(*) FROM tools")->fetchColumn(),
            'total_bookings' => (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
            'active_rentals' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND NOW() BETWEEN pick_up_datetime AND drop_off_datetime")->fetchColumn(),
            'upcoming_rentals' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND pick_up_datetime > NOW()")->fetchColumn(),
            'overdue_returns' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND drop_off_datetime < NOW()")->fetchColumn(),
            'completed_rentals' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn(),
            'awaiting_return' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NOT NULL AND return_reviewed_at IS NULL")->fetchColumn(),
            'disputes_raised' => (int)$pdo->query("SELECT COUNT(*) FROM disputes")->fetchColumn(),
            'open_disputes' => (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status IN ('pending','reviewing')")->fetchColumn(),
            'deposits_held' => (float)$pdo->query("SELECT COALESCE(SUM(security_deposit - deposit_refund_amount), 0) FROM bookings WHERE deposit_status = 'held'")->fetchColumn(),
            'deposits_deducted' => (float)$pdo->query("SELECT COALESCE(SUM(deposit_deducted), 0) FROM disputes WHERE status = 'resolved'")->fetchColumn(),
        ];

        $completedDeposits = (float)$pdo->query("SELECT COALESCE(SUM(deposit_refund_amount), 0) FROM bookings WHERE deposit_status IN ('full_refund', 'partial_refund')")->fetchColumn();
        $grossCompleted = (float)$pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status = 'completed'")->fetchColumn();
        $grossPending = (float)$pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status = 'paid' AND returned_at IS NULL")->fetchColumn();
        $grossPlatform = (float)$pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status IN ('paid','completed')")->fetchColumn();

        $overview['deposits_refunded'] = round($completedDeposits, 2);
        $overview['platform_earnings'] = round($grossPlatform * 0.06, 2);
        $overview['released_payouts'] = round($grossCompleted * 0.97, 2);
        $overview['pending_payouts'] = round($grossPending * 0.97, 2);

        return $overview;
    }

    function toolshare_admin_notifications(PDO $pdo, int $limit = 8): array
    {
        $items = [];

        $disputes = $pdo->query("
            SELECT d.id, d.reason, d.status, d.created_at, t.title, COALESCE(d.initiated_by, 'owner') AS initiated_by
            FROM disputes d
            JOIN tools t ON d.tool_id = t.id
            WHERE d.status IN ('pending','reviewing')
            ORDER BY d.created_at DESC
            LIMIT " . (int)$limit
        )->fetchAll();

        foreach ($disputes as $dispute) {
            $items[] = [
                'type' => 'dispute',
                'label' => 'Dispute: ' . $dispute['title'],
                'meta' => ucfirst((string)$dispute['status']) . ' - ' . $dispute['reason'],
                'href' => 'admin_dispute_case.php?id=' . (int)$dispute['id'] . '&queue=' . urlencode((string)$dispute['initiated_by']),
                'created_at' => $dispute['created_at'],
            ];
        }

        $returns = $pdo->query("
            SELECT b.id, b.returned_at, t.title
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            WHERE b.status = 'paid'
              AND b.returned_at IS NOT NULL
              AND b.return_reviewed_at IS NULL
            ORDER BY b.returned_at DESC
            LIMIT " . (int)$limit
        )->fetchAll();

        foreach ($returns as $booking) {
            $items[] = [
                'type' => 'return',
                'label' => 'Return review needed',
                'meta' => $booking['title'] . ' returned on ' . date('M d, Y h:i A', strtotime($booking['returned_at'])),
                'href' => 'admin_bookings.php?search=' . (int)$booking['id'],
                'created_at' => $booking['returned_at'],
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strtotime((string)$b['created_at']) <=> strtotime((string)$a['created_at']);
        });

        return array_slice($items, 0, $limit);
    }


    function toolshare_admin_chart_booking_status(PDO $pdo): array
    {
        return $pdo->query("
            SELECT status, COUNT(*) AS total
            FROM bookings
            GROUP BY status
            ORDER BY total DESC
        ")->fetchAll();
    }

    function toolshare_admin_chart_dispute_status(PDO $pdo): array
    {
        return $pdo->query("
            SELECT status, COUNT(*) AS total
            FROM disputes
            GROUP BY status
            ORDER BY total DESC
        ")->fetchAll();
    }

    function toolshare_admin_chart_monthly_revenue(PDO $pdo): array
    {
        return $pdo->query("
            SELECT DATE_FORMAT(pick_up_datetime, '%b %Y') AS label, COALESCE(SUM(total_price), 0) AS revenue
            FROM bookings
            WHERE status IN ('paid','completed')
              AND pick_up_datetime >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY YEAR(pick_up_datetime), MONTH(pick_up_datetime)
            ORDER BY YEAR(pick_up_datetime), MONTH(pick_up_datetime)
        ")->fetchAll();
    }

    function toolshare_admin_chart_monthly_bookings(PDO $pdo): array
    {
        return $pdo->query("
            SELECT DATE_FORMAT(created_at, '%b %Y') AS label, COUNT(*) AS total
            FROM bookings
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at)
            ORDER BY YEAR(created_at), MONTH(created_at)
        ")->fetchAll();
    }

    function toolshare_admin_fetch_users(PDO $pdo, string $search = ''): array
    {
        $term = '%' . $search . '%';
        $roleExpr = toolshare_admin_user_role_expr($pdo, 'u');
        $statusExpr = toolshare_admin_user_status_expr($pdo, 'u');
        $riskExpr = toolshare_admin_user_risk_expr($pdo, 'u');
        $verifiedExpr = toolshare_admin_user_verified_expr($pdo, 'u');
        $warningExpr = toolshare_admin_user_warning_expr($pdo, 'u');
        $phoneExpr = toolshare_admin_nullable_user_column($pdo, 'phone', 'u');
        $createdExpr = toolshare_admin_nullable_user_column($pdo, 'created_at', 'u');

        $sql = "
            SELECT
                u.id,
                u.full_name,
                u.email,
                {$phoneExpr} AS phone,
                {$createdExpr} AS created_at,
                {$roleExpr} AS role_name,
                {$statusExpr} AS account_status,
                {$riskExpr} AS risk_level,
                {$verifiedExpr} AS owner_verified,
                {$warningExpr} AS warning_count,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE b.renter_id = u.id
                ) AS renter_booking_count,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE b.owner_id = u.id
                ) AS owner_booking_count,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE b.renter_id = u.id OR b.owner_id = u.id
                ) AS booking_count,
                (
                    SELECT COUNT(*)
                    FROM tools t
                    WHERE t.owner_id = u.id
                ) AS tool_count,
                (
                    SELECT COUNT(*)
                    FROM disputes d
                    WHERE d.renter_id = u.id OR d.owner_id = u.id
                ) AS dispute_count,
                (
                    SELECT COUNT(*)
                    FROM disputes d
                    WHERE (d.renter_id = u.id OR d.owner_id = u.id)
                      AND d.status IN ('pending','reviewing')
                ) AS open_dispute_count
            FROM users u
            WHERE CAST(u.id AS CHAR) LIKE ?
               OR u.full_name LIKE ?
               OR u.email LIKE ?
            ORDER BY
                CASE {$statusExpr}
                    WHEN 'blocked' THEN 0
                    WHEN 'suspended' THEN 1
                    ELSE 2
                END,
                CASE {$riskExpr}
                    WHEN 'repeat_offender' THEN 0
                    WHEN 'watch' THEN 1
                    ELSE 2
                END,
                u.id DESC
            LIMIT 200
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$term, $term, $term]);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_user_profile(PDO $pdo, int $userId): ?array
    {
        $roleExpr = toolshare_admin_user_role_expr($pdo, 'u');
        $statusExpr = toolshare_admin_user_status_expr($pdo, 'u');
        $riskExpr = toolshare_admin_user_risk_expr($pdo, 'u');
        $verifiedExpr = toolshare_admin_user_verified_expr($pdo, 'u');
        $warningExpr = toolshare_admin_user_warning_expr($pdo, 'u');

        $sql = "
            SELECT
                u.id,
                u.full_name,
                u.email,
                " . toolshare_admin_nullable_user_column($pdo, 'phone', 'u') . " AS phone,
                {$roleExpr} AS role_name,
                {$statusExpr} AS account_status,
                {$riskExpr} AS risk_level,
                {$verifiedExpr} AS owner_verified,
                {$warningExpr} AS warning_count,
                " . toolshare_admin_nullable_user_column($pdo, 'status_reason', 'u') . " AS status_reason,
                " . toolshare_admin_nullable_user_column($pdo, 'last_warned_at', 'u') . " AS last_warned_at,
                " . toolshare_admin_nullable_user_column($pdo, 'verified_owner_at', 'u') . " AS verified_owner_at,
                " . toolshare_admin_nullable_user_column($pdo, 'last_suspended_at', 'u') . " AS last_suspended_at,
                " . toolshare_admin_nullable_user_column($pdo, 'last_blocked_at', 'u') . " AS last_blocked_at,
                " . toolshare_admin_nullable_user_column($pdo, 'created_at', 'u') . " AS created_at,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE b.renter_id = u.id
                ) AS renter_booking_count,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE b.owner_id = u.id
                ) AS owner_booking_count,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE (b.renter_id = u.id OR b.owner_id = u.id)
                      AND b.status = 'completed'
                ) AS completed_booking_count,
                (
                    SELECT COUNT(*)
                    FROM tools t
                    WHERE t.owner_id = u.id
                ) AS listing_count,
                (
                    SELECT COUNT(*)
                    FROM disputes d
                    WHERE d.renter_id = u.id OR d.owner_id = u.id
                ) AS dispute_count,
                (
                    SELECT COUNT(*)
                    FROM disputes d
                    WHERE (d.renter_id = u.id OR d.owner_id = u.id)
                      AND d.status IN ('pending','reviewing')
                ) AS open_dispute_count
            FROM users u
            WHERE u.id = ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            return null;
        }

        if (toolshare_admin_reviews_available($pdo)) {
            $reviewStats = $pdo->prepare("
                SELECT
                    COUNT(*) AS review_count,
                    ROUND(AVG(rating), 1) AS average_rating
                FROM reviews
                WHERE reviewer_id = ?
            ");
            $reviewStats->execute([$userId]);
            $stats = $reviewStats->fetch() ?: [];
            $profile['review_count'] = (int)($stats['review_count'] ?? 0);
            $profile['average_rating'] = $stats['average_rating'];
        } else {
            $profile['review_count'] = 0;
            $profile['average_rating'] = null;
        }

        return $profile;
    }

    function toolshare_admin_fetch_user_booking_history(PDO $pdo, int $userId, int $limit = 12): array
    {
        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.status,
                b.total_price,
                b.pick_up_datetime,
                b.drop_off_datetime,
                b.returned_at,
                b.created_at,
                t.title AS tool_title,
                renter.full_name AS renter_name,
                owner.full_name AS owner_name,
                CASE
                    WHEN b.renter_id = ? THEN 'renter'
                    ELSE 'owner'
                END AS user_side
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            JOIN users renter ON b.renter_id = renter.id
            JOIN users owner ON b.owner_id = owner.id
            WHERE b.renter_id = ? OR b.owner_id = ?
            ORDER BY COALESCE(b.created_at, b.pick_up_datetime) DESC, b.id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_user_listings(PDO $pdo, int $userId, int $limit = 10): array
    {
        $stmt = $pdo->prepare("
            SELECT
                t.id,
                t.title,
                t.category,
                t.brand,
                t.price_daily,
                t.price_hourly,
                t.price_weekly,
                t.created_at
            FROM tools t
            WHERE t.owner_id = ?
            ORDER BY t.id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_user_disputes(PDO $pdo, int $userId, int $limit = 12): array
    {
        $stmt = $pdo->prepare("
            SELECT
                d.id,
                d.booking_id,
                d.reason,
                d.status,
                d.admin_decision,
                d.deposit_held,
                d.deposit_deducted,
                d.created_at,
                t.title AS tool_title,
                owner.full_name AS owner_name,
                renter.full_name AS renter_name,
                CASE
                    WHEN d.owner_id = ? THEN 'raised_by_user'
                    ELSE 'against_user'
                END AS dispute_relation
            FROM disputes d
            JOIN tools t ON d.tool_id = t.id
            JOIN users owner ON d.owner_id = owner.id
            JOIN users renter ON d.renter_id = renter.id
            WHERE d.renter_id = ? OR d.owner_id = ?
            ORDER BY d.created_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_user_reviews(PDO $pdo, int $userId, int $limit = 8): array
    {
        if (!toolshare_admin_reviews_available($pdo)) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.rating,
                r.comment,
                r.created_at,
                t.title AS tool_title,
                r.booking_id
            FROM reviews r
            JOIN tools t ON r.tool_id = t.id
            WHERE r.reviewer_id = ?
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_user_admin_actions(PDO $pdo, int $userId, int $limit = 20): array
    {
        if (!toolshare_table_exists($pdo, 'user_admin_actions')) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                a.*,
                admin.full_name AS admin_name
            FROM user_admin_actions a
            LEFT JOIN users admin ON a.admin_id = admin.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $decoded = json_decode((string)($row['metadata'] ?? ''), true);
            $row['metadata_array'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return $rows;
    }

    function toolshare_admin_record_user_action(PDO $pdo, int $userId, ?int $adminId, string $actionType, ?string $notes = null, array $metadata = []): void
    {
        if (!toolshare_table_exists($pdo, 'user_admin_actions')) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_admin_actions (user_id, admin_id, action_type, notes, metadata)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $adminId,
            $actionType,
            $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            !empty($metadata) ? json_encode($metadata) : null,
        ]);
    }

    function toolshare_admin_update_user_moderation(PDO $pdo, int $userId, int $adminId, string $action, array $payload = []): string
    {
        $profile = toolshare_admin_fetch_user_profile($pdo, $userId);
        if (!$profile) {
            throw new RuntimeException('User not found.');
        }

        $notes = trim((string)($payload['notes'] ?? ''));
        $riskLevel = trim((string)($payload['risk_level'] ?? ''));

        if ($adminId === $userId && in_array($action, ['suspend', 'block'], true)) {
            throw new RuntimeException('You cannot suspend or block your own account.');
        }

        $pdo->beginTransaction();

        try {
            switch ($action) {
                case 'warn':
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET warning_count = COALESCE(warning_count, 0) + 1,
                            last_warned_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId]);
                    toolshare_admin_record_user_action($pdo, $userId, $adminId, 'warned_user', $notes, [
                        'warnings_after' => (int)$profile['warning_count'] + 1,
                    ]);
                    $message = 'User warning recorded.';
                    break;

                case 'suspend':
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET account_status = 'suspended',
                            status_reason = ?,
                            last_suspended_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$notes !== '' ? $notes : null, $userId]);
                    toolshare_admin_record_user_action($pdo, $userId, $adminId, 'suspended_user', $notes, [
                        'previous_status' => $profile['account_status'],
                        'new_status' => 'suspended',
                    ]);
                    $message = 'User suspended.';
                    break;

                case 'block':
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET account_status = 'blocked',
                            status_reason = ?,
                            last_blocked_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$notes !== '' ? $notes : null, $userId]);
                    toolshare_admin_record_user_action($pdo, $userId, $adminId, 'blocked_user', $notes, [
                        'previous_status' => $profile['account_status'],
                        'new_status' => 'blocked',
                    ]);
                    $message = 'User blocked.';
                    break;

                case 'reactivate':
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET account_status = 'active',
                            status_reason = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId]);
                    toolshare_admin_record_user_action($pdo, $userId, $adminId, 'reactivated_user', $notes, [
                        'previous_status' => $profile['account_status'],
                        'new_status' => 'active',
                    ]);
                    $message = 'User reactivated.';
                    break;

                case 'verify_owner':
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET owner_verified = 1,
                            verified_owner_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId]);
                    toolshare_admin_record_user_action($pdo, $userId, $adminId, 'verified_owner', $notes);
                    $message = 'Owner account verified.';
                    break;

                case 'unverify_owner':
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET owner_verified = 0
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId]);
                    toolshare_admin_record_user_action($pdo, $userId, $adminId, 'removed_owner_verification', $notes);
                    $message = 'Owner verification removed.';
                    break;

                case 'set_risk':
                    if (!in_array($riskLevel, ['normal', 'watch', 'repeat_offender'], true)) {
                        throw new RuntimeException('Invalid risk level selected.');
                    }

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET risk_level = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$riskLevel, $userId]);
                    toolshare_admin_record_user_action($pdo, $userId, $adminId, 'updated_risk_level', $notes, [
                        'previous_risk' => $profile['risk_level'],
                        'new_risk' => $riskLevel,
                    ]);
                    $message = $riskLevel === 'normal' ? 'Risk flag cleared.' : 'Risk level updated.';
                    break;

                default:
                    throw new RuntimeException('Unknown moderation action.');
            }

            $pdo->commit();
            return $message;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    function toolshare_admin_fetch_listings(PDO $pdo, string $search = ''): array
    {
        $term = '%' . $search . '%';
        $stmt = $pdo->prepare("
            SELECT
                t.*,
                u.full_name AS owner_name
            FROM tools t
            JOIN users u ON t.owner_id = u.id
            WHERE t.title LIKE ? OR u.full_name LIKE ? OR t.category LIKE ?
            ORDER BY t.id DESC
            LIMIT 200
        ");
        $stmt->execute([$term, $term, $term]);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_bookings(PDO $pdo, array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        $params = [];
        $sql = "
            SELECT
                b.*,
                t.title AS tool_title,
                renter.full_name AS renter_name,
                owner.full_name AS owner_name,
                COALESCE((
                    SELECT d.status
                    FROM disputes d
                    WHERE d.booking_id = b.id
                    ORDER BY d.created_at DESC
                    LIMIT 1
                ), 'none') AS dispute_status,
                COALESCE((
                    SELECT d.admin_decision
                    FROM disputes d
                    WHERE d.booking_id = b.id
                    ORDER BY d.created_at DESC
                    LIMIT 1
                ), 'pending') AS settlement_status
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            JOIN users renter ON b.renter_id = renter.id
            JOIN users owner ON b.owner_id = owner.id
            WHERE 1 = 1
        ";

        if ($search !== '') {
            $term = '%' . $search . '%';
            $sql .= " AND (
                CAST(b.id AS CHAR) LIKE ?
                OR t.title LIKE ?
                OR renter.full_name LIKE ?
                OR owner.full_name LIKE ?
            ) ";
            array_push($params, $term, $term, $term, $term);
        }

        if ($status !== '') {
            $sql .= " AND b.status = ? ";
            $params[] = $status;
        }

        $sql .= " ORDER BY b.id DESC LIMIT 250";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_disputes(PDO $pdo, array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        $userId = (int)($filters['user_id'] ?? 0);
        $queue = trim((string)($filters['queue'] ?? 'owner'));
        $queue = in_array($queue, ['owner', 'renter'], true) ? $queue : 'owner';
        $params = [];
        $sql = "
            SELECT
                d.*,
                t.title AS tool_title,
                b.pick_up_datetime,
                b.drop_off_datetime,
                b.total_price,
                b.security_deposit,
                b.deposit_status,
                b.deposit_refund_amount,
                owner.full_name AS owner_name,
                renter.full_name AS renter_name
            FROM disputes d
            JOIN tools t ON d.tool_id = t.id
            JOIN bookings b ON d.booking_id = b.id
            JOIN users owner ON d.owner_id = owner.id
            JOIN users renter ON d.renter_id = renter.id
            WHERE 1 = 1
        ";

        $sql .= " AND COALESCE(d.initiated_by, 'owner') = ? ";
        $params[] = $queue;

        if ($search !== '') {
            $term = '%' . $search . '%';
            $sql .= " AND (
                CAST(d.id AS CHAR) LIKE ?
                OR CAST(d.booking_id AS CHAR) LIKE ?
                OR t.title LIKE ?
                OR owner.full_name LIKE ?
                OR renter.full_name LIKE ?
                OR d.reason LIKE ?
            ) ";
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        if ($status !== '') {
            $sql .= " AND d.status = ? ";
            $params[] = $status;
        }

        if ($userId > 0) {
            $sql .= " AND (d.renter_id = ? OR d.owner_id = ?) ";
            array_push($params, $userId, $userId);
        }

        $sql .= " ORDER BY d.created_at DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_dispute(PDO $pdo, int $disputeId, array $filters = []): ?array
    {
        $queue = trim((string)($filters['queue'] ?? ''));
        $queue = in_array($queue, ['owner', 'renter'], true) ? $queue : '';
        $params = [$disputeId];
        $sql = "
            SELECT
                d.*,
                t.title AS tool_title,
                b.pick_up_datetime,
                b.drop_off_datetime,
                b.total_price,
                b.security_deposit,
                b.deposit_status,
                b.deposit_refund_amount,
                owner.full_name AS owner_name,
                renter.full_name AS renter_name
            FROM disputes d
            JOIN tools t ON d.tool_id = t.id
            JOIN bookings b ON d.booking_id = b.id
            JOIN users owner ON d.owner_id = owner.id
            JOIN users renter ON d.renter_id = renter.id
            WHERE d.id = ?
        ";

        if ($queue !== '') {
            $sql .= " AND COALESCE(d.initiated_by, 'owner') = ? ";
            $params[] = $queue;
        }

        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function toolshare_admin_fetch_dispute_history(PDO $pdo, int $disputeId, int $limit = 25): array
    {
        if (!toolshare_table_exists($pdo, 'dispute_history')) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                dh.*,
                admin.full_name AS admin_name
            FROM dispute_history dh
            LEFT JOIN users admin ON dh.admin_id = admin.id
            WHERE dh.dispute_id = ?
            ORDER BY dh.created_at DESC, dh.id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$disputeId]);
        return $stmt->fetchAll();
    }

    function toolshare_admin_fetch_deposits(PDO $pdo): array
    {
        return $pdo->query("
            SELECT
                b.id AS booking_id,
                t.title AS tool_title,
                owner.full_name AS owner_name,
                renter.full_name AS renter_name,
                b.security_deposit,
                b.status AS booking_status,
                b.deposit_status AS deposit_outcome,
                b.deposit_refund_amount,
                COALESCE((
                    SELECT d.deposit_deducted
                    FROM disputes d
                    WHERE d.booking_id = b.id
                    ORDER BY d.created_at DESC
                    LIMIT 1
                ), 0) AS deducted_amount
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            JOIN users owner ON b.owner_id = owner.id
            JOIN users renter ON b.renter_id = renter.id
            ORDER BY b.id DESC
            LIMIT 250
        ")->fetchAll();
    }

    function toolshare_admin_fetch_payouts(PDO $pdo): array
    {
        return $pdo->query("
            SELECT
                b.id AS booking_id,
                t.title AS tool_title,
                owner.full_name AS owner_name,
                b.total_price,
                b.status AS booking_status,
                ROUND(b.total_price * 0.03, 2) AS owner_commission,
                ROUND(b.total_price * 0.97, 2) AS owner_payout,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM disputes d
                        WHERE d.booking_id = b.id
                          AND d.status IN ('pending', 'reviewing')
                    ) THEN 'on_hold'
                    WHEN b.status = 'completed' THEN 'released'
                    WHEN b.status = 'paid' THEN 'pending'
                    ELSE 'not_ready'
                END AS payout_status
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            JOIN users owner ON b.owner_id = owner.id
            ORDER BY b.id DESC
            LIMIT 250
        ")->fetchAll();
    }

    function toolshare_admin_fetch_earnings(PDO $pdo): array
    {
        return $pdo->query("
            SELECT
                b.id AS booking_id,
                t.title AS tool_title,
                b.total_price,
                b.status,
                ROUND(b.total_price * 0.03, 2) AS renter_commission,
                ROUND(b.total_price * 0.03, 2) AS owner_commission,
                ROUND(b.total_price * 0.06, 2) AS platform_total
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            ORDER BY b.id DESC
            LIMIT 250
        ")->fetchAll();
    }

    function toolshare_admin_fetch_top_tools(PDO $pdo): array
    {
        return $pdo->query("
            SELECT t.title, COUNT(*) AS rentals, COALESCE(SUM(b.total_price), 0) AS revenue
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            GROUP BY b.tool_id, t.title
            ORDER BY rentals DESC, revenue DESC
            LIMIT 8
        ")->fetchAll();
    }

    function toolshare_admin_needs_action(PDO $pdo): array
    {
        return [
            'pending_disputes' => (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'pending'")->fetchColumn(),
            'reviewing_disputes' => (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'reviewing'")->fetchColumn(),
            'awaiting_return' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NOT NULL AND return_reviewed_at IS NULL")->fetchColumn(),
            'pending_payouts' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL")->fetchColumn(),
            'overdue_returns' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid' AND returned_at IS NULL AND drop_off_datetime < NOW()")->fetchColumn(),
        ];
    }

    function toolshare_dispute_evidence_list(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
