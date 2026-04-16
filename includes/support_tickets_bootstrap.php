<?php

if (!function_exists('toolshare_bootstrap_support_tickets')) {
    function toolshare_bootstrap_support_tickets(PDO $pdo): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS support_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                status ENUM('open','resolved') NOT NULL DEFAULT 'open',
                subject VARCHAR(190) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                replied_at DATETIME NULL,
                replied_by_admin_id INT NULL,
                INDEX idx_support_tickets_status (status),
                INDEX idx_support_tickets_user (user_id),
                INDEX idx_support_tickets_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $statusColumn = $pdo->query("SHOW COLUMNS FROM `support_tickets` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        $statusType = strtolower((string)($statusColumn['Type'] ?? ''));
        if (strpos($statusType, 'waiting_on_support') !== false || strpos($statusType, 'waiting_on_user') !== false) {
            $pdo->exec("UPDATE support_tickets SET status = 'open' WHERE status IN ('waiting_on_support', 'waiting_on_user')");
            $pdo->exec("ALTER TABLE `support_tickets` MODIFY COLUMN `status` ENUM('open','resolved') NOT NULL DEFAULT 'open'");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS support_ticket_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                sender_type ENUM('user','support') NOT NULL,
                sender_user_id INT NULL,
                sender_label VARCHAR(120) NULL,
                message_text TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_support_ticket_messages_ticket (ticket_id),
                INDEX idx_support_ticket_messages_read (is_read),
                CONSTRAINT fk_support_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $bootstrapped = true;
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        toolshare_bootstrap_support_tickets($pdo);
    }
}
