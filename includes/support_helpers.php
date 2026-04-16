<?php
require_once __DIR__ . '/support_tickets_bootstrap.php';
require_once __DIR__ . '/marketplace_mail_helper.php';

if (!function_exists('toolshare_support_find_user_id_by_email')) {
    function toolshare_support_status_label(string $status): string
    {
        return $status === 'resolved' ? 'Closed' : 'Open';
    }

    function toolshare_support_find_user_id_by_email(PDO $pdo, string $email): ?int
    {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $value = $stmt->fetchColumn();
        return $value ? (int)$value : null;
    }

    function toolshare_support_submit_ticket(PDO $pdo, array $payload): int
    {
        toolshare_bootstrap_support_tickets($pdo);

        $name = trim((string)($payload['name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        $userId = isset($payload['user_id']) && (int)$payload['user_id'] > 0
            ? (int)$payload['user_id']
            : toolshare_support_find_user_id_by_email($pdo, $email);

        if ($name === '' || $email === '' || $message === '') {
            throw new InvalidArgumentException('Name, email, and message are required.');
        }

        $pdo->beginTransaction();
        try {
            $ticket = $pdo->prepare("
                INSERT INTO support_tickets (user_id, name, email, status)
                VALUES (?, ?, ?, 'open')
            ");
            $ticket->execute([$userId, $name, $email]);
            $ticketId = (int)$pdo->lastInsertId();

            $messageStmt = $pdo->prepare("
                INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_user_id, sender_label, message_text, is_read)
                VALUES (?, 'user', ?, ?, ?, 1)
            ");
            $messageStmt->execute([$ticketId, $userId, $name, $message]);

            $pdo->commit();
            toolshare_mail_send_support_ticket_created_notification($pdo, $ticketId);
            return $ticketId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    function toolshare_support_mark_user_messages_read(PDO $pdo, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE support_ticket_messages stm
            JOIN support_tickets st ON st.id = stm.ticket_id
            SET stm.is_read = 1
            WHERE st.user_id = ?
              AND stm.sender_type = 'support'
              AND stm.is_read = 0
        ");
        $stmt->execute([$userId]);
    }

    function toolshare_support_fetch_user_tickets(PDO $pdo, int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                st.*,
                last_msg.message_text AS last_message_text,
                last_msg.sender_type AS last_message_sender_type,
                last_msg.sender_label AS last_message_sender_label,
                last_msg.created_at AS last_message_at,
                COALESCE(unread_counts.unread_count, 0) AS unread_count
            FROM support_tickets st
            LEFT JOIN support_ticket_messages last_msg
                ON last_msg.id = (
                    SELECT stm2.id
                    FROM support_ticket_messages stm2
                    WHERE stm2.ticket_id = st.id
                    ORDER BY stm2.created_at DESC, stm2.id DESC
                    LIMIT 1
                )
            LEFT JOIN (
                SELECT
                    st2.id AS ticket_id,
                    COUNT(*) AS unread_count
                FROM support_tickets st2
                JOIN support_ticket_messages stm3 ON stm3.ticket_id = st2.id
                WHERE st2.user_id = ?
                  AND stm3.sender_type = 'support'
                  AND stm3.is_read = 0
                GROUP BY st2.id
            ) unread_counts ON unread_counts.ticket_id = st.id
            WHERE st.user_id = ?
            ORDER BY COALESCE(last_msg.created_at, st.updated_at) DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    function toolshare_support_fetch_recent_threads(PDO $pdo, int $userId, int $limit = 5): array
    {
        $tickets = toolshare_support_fetch_user_tickets($pdo, $userId, $limit);
        $threads = [];

        foreach ($tickets as $ticket) {
            $preview = (string)($ticket['last_message_text'] ?? '');
            $preview = mb_strlen($preview) > 68 ? mb_substr($preview, 0, 68) . '...' : $preview;
            $threads[] = [
                'thread_type' => 'support',
                'ticket_id' => (int)$ticket['id'],
                'name' => 'ToolShare Support',
                'subtitle' => 'Support Ticket #' . (int)$ticket['id'],
                'preview' => $preview,
                'created_at' => (string)($ticket['last_message_at'] ?? $ticket['updated_at']),
                'unread_count' => (int)$ticket['unread_count'],
                'href' => 'support_chat.php?ticket_id=' . (int)$ticket['id'],
            ];
        }

        return $threads;
    }

    function toolshare_support_count_unread(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM support_ticket_messages stm
            JOIN support_tickets st ON st.id = stm.ticket_id
            WHERE st.user_id = ?
              AND stm.sender_type = 'support'
              AND stm.is_read = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    function toolshare_support_fetch_admin_tickets(PDO $pdo, array $filters = []): array
    {
        toolshare_bootstrap_support_tickets($pdo);

        $status = trim((string)($filters['status'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $params = [];
        $sql = "
            SELECT
                st.*,
                last_msg.message_text AS last_message_text,
                last_msg.sender_type AS last_message_sender_type,
                last_msg.sender_label AS last_message_sender_label,
                last_msg.created_at AS last_message_at,
                COALESCE(unread_counts.unread_count, 0) AS unread_count
            FROM support_tickets st
            LEFT JOIN support_ticket_messages last_msg
                ON last_msg.id = (
                    SELECT stm2.id
                    FROM support_ticket_messages stm2
                    WHERE stm2.ticket_id = st.id
                    ORDER BY stm2.created_at DESC, stm2.id DESC
                    LIMIT 1
                )
            LEFT JOIN (
                SELECT ticket_id, COUNT(*) AS unread_count
                FROM support_ticket_messages
                WHERE sender_type = 'user'
                  AND is_read = 0
                GROUP BY ticket_id
            ) unread_counts ON unread_counts.ticket_id = st.id
            WHERE 1 = 1
        ";

        if ($status !== '') {
            $sql .= " AND st.status = ? ";
            $params[] = $status;
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $sql .= " AND (
                CAST(st.id AS CHAR) LIKE ?
                OR st.name LIKE ?
                OR st.email LIKE ?
                OR last_msg.message_text LIKE ?
            ) ";
            array_push($params, $term, $term, $term, $term);
        }

        $sql .= " ORDER BY COALESCE(last_msg.created_at, st.updated_at) DESC LIMIT 250";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    function toolshare_support_fetch_admin_ticket(PDO $pdo, int $ticketId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT st.*
            FROM support_tickets st
            WHERE st.id = ?
            LIMIT 1
        ");
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function toolshare_support_fetch_user_ticket(PDO $pdo, int $ticketId, int $userId): ?array
    {
        if ($ticketId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT st.*
            FROM support_tickets st
            WHERE st.id = ?
              AND st.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$ticketId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function toolshare_support_fetch_ticket_messages(PDO $pdo, int $ticketId): array
    {
        $stmt = $pdo->prepare("
            SELECT *
            FROM support_ticket_messages
            WHERE ticket_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    function toolshare_support_mark_ticket_messages_read(PDO $pdo, int $ticketId, int $userId): void
    {
        if ($ticketId <= 0 || $userId <= 0) {
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE support_ticket_messages stm
            JOIN support_tickets st ON st.id = stm.ticket_id
            SET stm.is_read = 1
            WHERE st.id = ?
              AND st.user_id = ?
              AND stm.sender_type = 'support'
              AND stm.is_read = 0
        ");
        $stmt->execute([$ticketId, $userId]);
    }

    function toolshare_support_reply_ticket(PDO $pdo, int $ticketId, int $adminId, string $message, string $status = 'waiting_on_user'): void
    {
        toolshare_bootstrap_support_tickets($pdo);

        $message = trim($message);
        if ($ticketId <= 0 || $adminId <= 0 || $message === '') {
            throw new InvalidArgumentException('Ticket, admin, and reply are required.');
        }

        $allowedStatuses = ['open', 'resolved'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'open';
        }

        $pdo->beginTransaction();
        try {
            $ticket = $pdo->prepare("SELECT id, status FROM support_tickets WHERE id = ? LIMIT 1");
            $ticket->execute([$ticketId]);
            $ticketRow = $ticket->fetch();
            if (!$ticketRow) {
                throw new RuntimeException('Support ticket not found.');
            }
            if ((string)$ticketRow['status'] === 'resolved') {
                throw new RuntimeException('This support ticket is already closed.');
            }

            $replyStmt = $pdo->prepare("
                INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_user_id, sender_label, message_text, is_read)
                VALUES (?, 'support', ?, 'ToolShare Support', ?, 0)
            ");
            $replyStmt->execute([$ticketId, $adminId, $message]);

            $update = $pdo->prepare("
                UPDATE support_tickets
                SET status = ?,
                    replied_at = NOW(),
                    replied_by_admin_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$status, $adminId, $ticketId]);

            $markUserMessagesRead = $pdo->prepare("
                UPDATE support_ticket_messages
                SET is_read = 1
                WHERE ticket_id = ?
                  AND sender_type = 'user'
                  AND is_read = 0
            ");
            $markUserMessagesRead->execute([$ticketId]);

            $pdo->commit();
            toolshare_mail_send_support_reply_notification($pdo, $ticketId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    function toolshare_support_reply_as_user(PDO $pdo, int $ticketId, int $userId, string $message): void
    {
        toolshare_bootstrap_support_tickets($pdo);

        $message = trim($message);
        if ($ticketId <= 0 || $userId <= 0 || $message === '') {
            throw new InvalidArgumentException('Ticket, user, and message are required.');
        }

        $pdo->beginTransaction();
        try {
            $ticketStmt = $pdo->prepare("
                SELECT id, name, status
                FROM support_tickets
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");
            $ticketStmt->execute([$ticketId, $userId]);
            $ticket = $ticketStmt->fetch();
            if (!$ticket) {
                throw new RuntimeException('Support ticket not found.');
            }
            if ((string)$ticket['status'] === 'resolved') {
                throw new RuntimeException('This support ticket is already closed.');
            }

            $replyStmt = $pdo->prepare("
                INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_user_id, sender_label, message_text, is_read)
                VALUES (?, 'user', ?, ?, ?, 0)
            ");
            $replyStmt->execute([$ticketId, $userId, (string)$ticket['name'], $message]);

            $update = $pdo->prepare("
                UPDATE support_tickets
                SET status = 'open',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$ticketId]);

            $markSupportRead = $pdo->prepare("
                UPDATE support_ticket_messages
                SET is_read = 1
                WHERE ticket_id = ?
                  AND sender_type = 'support'
                  AND is_read = 0
            ");
            $markSupportRead->execute([$ticketId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
