<?php
require_once __DIR__ . '/support_helpers.php';

if (!function_exists('toolshare_bootstrap_notification_views')) {
    function toolshare_bootstrap_notification_views(PDO $pdo): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notification_views (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                audience ENUM('user','admin') NOT NULL,
                item_type VARCHAR(40) NOT NULL,
                item_key VARCHAR(120) NOT NULL,
                viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_notification_view (user_id, audience, item_type, item_key),
                INDEX idx_notification_view_user (user_id, audience)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $bootstrapped = true;
    }

    function toolshare_mark_notification_item_viewed(PDO $pdo, int $userId, string $audience, string $itemType, string $itemKey): void
    {
        if ($userId <= 0 || $itemKey === '') {
            return;
        }

        toolshare_bootstrap_notification_views($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO notification_views (user_id, audience, item_type, item_key)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$userId, $audience, $itemType, $itemKey]);
    }

    function toolshare_mark_notification_items_viewed(PDO $pdo, int $userId, string $audience, string $itemType, array $itemKeys): void
    {
        $itemKeys = array_values(array_filter(array_map(static fn($value) => trim((string)$value), $itemKeys), static fn($value) => $value !== ''));
        if ($userId <= 0 || empty($itemKeys)) {
            return;
        }

        foreach ($itemKeys as $itemKey) {
            toolshare_mark_notification_item_viewed($pdo, $userId, $audience, $itemType, $itemKey);
        }
    }

    function toolshare_fetch_user_message_notifications(PDO $pdo, int $userId, int $limit = 8): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                m.id,
                m.tool_id,
                m.sender_id,
                m.receiver_id,
                m.message_text,
                m.created_at,
                t.title AS tool_title,
                u_sender.full_name AS sender_name,
                u_receiver.full_name AS receiver_name
            FROM messages m
            JOIN tools t ON m.tool_id = t.id
            JOIN users u_sender ON m.sender_id = u_sender.id
            JOIN users u_receiver ON m.receiver_id = u_receiver.id
            WHERE m.id IN (
                SELECT MAX(id)
                FROM messages
                WHERE receiver_id = ?
                  AND is_read = 0
                GROUP BY tool_id, sender_id
            )
            ORDER BY m.created_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $preview = mb_strlen((string)$row['message_text']) > 90 ? mb_substr((string)$row['message_text'], 0, 90) . '...' : (string)$row['message_text'];
            $items[] = [
                'type' => 'message',
                'label' => 'New message from ' . (string)$row['sender_name'],
                'meta' => (string)$row['tool_title'] . ' · ' . $preview,
                'href' => 'chat.php?tool_id=' . (int)$row['tool_id'] . '&receiver_id=' . (int)$row['sender_id'],
                'created_at' => (string)$row['created_at'],
                'is_unread' => true,
            ];
        }

        foreach (toolshare_support_fetch_recent_threads($pdo, $userId, $limit) as $thread) {
            if ((int)$thread['unread_count'] < 1) {
                continue;
            }
            $items[] = [
                'type' => 'support',
                'label' => 'Reply from ToolShare Support',
                'meta' => (string)$thread['subtitle'] . ' · ' . (string)$thread['preview'],
                'href' => (string)$thread['href'],
                'created_at' => (string)$thread['created_at'],
                'is_unread' => true,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });

        return array_slice($items, 0, $limit);
    }

    function toolshare_fetch_user_booking_request_notifications(PDO $pdo, int $userId, int $limit = 8): array
    {
        if ($userId <= 0) {
            return [];
        }

        toolshare_bootstrap_notification_views($pdo);

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.created_at,
                t.title AS tool_title,
                renter.full_name AS renter_name,
                CASE WHEN nv.id IS NULL THEN 0 ELSE 1 END AS is_seen
            FROM bookings b
            JOIN tools t ON b.tool_id = t.id
            JOIN users renter ON b.renter_id = renter.id
            LEFT JOIN notification_views nv
                ON nv.user_id = ?
               AND nv.audience = 'user'
               AND nv.item_type = 'booking_request'
               AND nv.item_key = CAST(b.id AS CHAR)
            WHERE b.owner_id = ?
              AND b.status = 'pending'
            ORDER BY b.created_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId, $userId]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'type' => 'booking_request',
                'item_key' => (string)$row['id'],
                'label' => 'New booking request for ' . (string)$row['tool_title'],
                'meta' => 'Requested by ' . (string)$row['renter_name'],
                'href' => 'dashboard.php#incoming-requests',
                'created_at' => (string)$row['created_at'],
                'is_unread' => (int)$row['is_seen'] === 0,
            ];
        }

        return $items;
    }

    function toolshare_fetch_user_notification_center(PDO $pdo, int $userId, int $limit = 12): array
    {
        $requestItems = toolshare_fetch_user_booking_request_notifications($pdo, $userId, $limit);
        $messageItems = toolshare_fetch_user_message_notifications($pdo, $userId, $limit);
        $activeRequestItems = array_values(array_filter(
            $requestItems,
            static fn(array $item): bool => !empty($item['is_unread'])
        ));
        $items = array_merge($activeRequestItems, $messageItems);

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });

        return [
            'items' => array_slice($items, 0, $limit),
            'unread_count' => count($items),
            'request_unread_count' => count($activeRequestItems),
            'message_unread_count' => count($messageItems),
        ];
    }

    function toolshare_mark_user_request_notifications_viewed(PDO $pdo, int $userId): void
    {
        $items = toolshare_fetch_user_booking_request_notifications($pdo, $userId, 100);
        $keys = [];
        foreach ($items as $item) {
            if (!empty($item['is_unread']) && isset($item['item_key'])) {
                $keys[] = (string)$item['item_key'];
            }
        }
        toolshare_mark_notification_items_viewed($pdo, $userId, 'user', 'booking_request', $keys);
    }

    function toolshare_fetch_admin_notification_center(PDO $pdo, int $adminId, int $limit = 12): array
    {
        if ($adminId <= 0) {
            return ['items' => [], 'unread_count' => 0, 'active_disputes' => 0, 'open_tickets' => 0];
        }

        toolshare_bootstrap_notification_views($pdo);
        toolshare_bootstrap_support_tickets($pdo);

        $disputeStmt = $pdo->prepare("
            SELECT
                d.id,
                d.reason,
                d.status,
                d.created_at,
                t.title AS tool_title,
                COALESCE(d.initiated_by, 'owner') AS initiated_by,
                CASE WHEN nv.id IS NULL THEN 0 ELSE 1 END AS is_seen
            FROM disputes d
            JOIN tools t ON d.tool_id = t.id
            LEFT JOIN notification_views nv
                ON nv.user_id = ?
               AND nv.audience = 'admin'
               AND nv.item_type = 'dispute'
               AND nv.item_key = CAST(d.id AS CHAR)
            WHERE d.status IN ('pending', 'reviewing')
            ORDER BY d.created_at DESC
            LIMIT " . (int)$limit
        );
        $disputeStmt->execute([$adminId]);
        $disputes = $disputeStmt->fetchAll();

        $ticketStmt = $pdo->prepare("
            SELECT
                st.id,
                st.status,
                st.created_at,
                st.name,
                st.email,
                CASE WHEN nv.id IS NULL THEN 0 ELSE 1 END AS is_seen
            FROM support_tickets st
            LEFT JOIN notification_views nv
                ON nv.user_id = ?
               AND nv.audience = 'admin'
               AND nv.item_type = 'ticket'
               AND nv.item_key = CAST(st.id AS CHAR)
            WHERE st.status = 'open'
            ORDER BY st.created_at DESC
            LIMIT " . (int)$limit
        );
        $ticketStmt->execute([$adminId]);
        $tickets = $ticketStmt->fetchAll();

        $items = [];
        foreach ($disputes as $dispute) {
            $items[] = [
                'type' => 'dispute',
                'item_key' => (string)$dispute['id'],
                'label' => 'New ' . ((string)$dispute['initiated_by'] === 'renter' ? 'renter' : 'owner') . ' dispute',
                'meta' => (string)$dispute['tool_title'] . ' · ' . (string)$dispute['reason'],
                'href' => 'admin_dispute_case.php?id=' . (int)$dispute['id'] . '&queue=' . urlencode((string)$dispute['initiated_by']),
                'created_at' => (string)$dispute['created_at'],
                'is_unread' => (int)$dispute['is_seen'] === 0,
            ];
        }
        foreach ($tickets as $ticket) {
            $items[] = [
                'type' => 'ticket',
                'item_key' => (string)$ticket['id'],
                'label' => 'New support ticket from ' . (string)$ticket['name'],
                'meta' => 'Ticket #' . (int)$ticket['id'] . ' · ' . (string)$ticket['email'],
                'href' => 'admin_tickets.php?ticket_id=' . (int)$ticket['id'],
                'created_at' => (string)$ticket['created_at'],
                'is_unread' => (int)$ticket['is_seen'] === 0,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });

        return [
            'items' => array_slice($items, 0, $limit),
            'unread_count' => count(array_filter($items, static fn(array $item): bool => !empty($item['is_unread']))),
            'active_disputes' => count($disputes),
            'open_tickets' => count($tickets),
        ];
    }

    function toolshare_mark_admin_notification_center_viewed(PDO $pdo, int $adminId): void
    {
        $center = toolshare_fetch_admin_notification_center($pdo, $adminId, 200);
        $disputeKeys = [];
        $ticketKeys = [];
        foreach ($center['items'] as $item) {
            if (empty($item['is_unread']) || empty($item['item_key'])) {
                continue;
            }
            if ($item['type'] === 'dispute') {
                $disputeKeys[] = (string)$item['item_key'];
            } elseif ($item['type'] === 'ticket') {
                $ticketKeys[] = (string)$item['item_key'];
            }
        }

        toolshare_mark_notification_items_viewed($pdo, $adminId, 'admin', 'dispute', $disputeKeys);
        toolshare_mark_notification_items_viewed($pdo, $adminId, 'admin', 'ticket', $ticketKeys);
    }
}
