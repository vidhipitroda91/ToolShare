<?php
if (isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/returns_bootstrap.php';

    toolshare_ensure_column($pdo, 'users', 'account_status', "ENUM('active','suspended','blocked') NOT NULL DEFAULT 'active' AFTER `password`");
    toolshare_ensure_column($pdo, 'users', 'status_reason', 'TEXT NULL AFTER `account_status`');
    toolshare_ensure_column($pdo, 'users', 'warning_count', 'INT NOT NULL DEFAULT 0 AFTER `status_reason`');
    toolshare_ensure_column($pdo, 'users', 'last_warned_at', 'DATETIME NULL AFTER `warning_count`');
    toolshare_ensure_column($pdo, 'users', 'risk_level', "ENUM('normal','watch','repeat_offender') NOT NULL DEFAULT 'normal' AFTER `last_warned_at`");
    toolshare_ensure_column($pdo, 'users', 'owner_verified', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `risk_level`');
    toolshare_ensure_column($pdo, 'users', 'verified_owner_at', 'DATETIME NULL AFTER `owner_verified`');
    toolshare_ensure_column($pdo, 'users', 'last_suspended_at', 'DATETIME NULL AFTER `verified_owner_at`');
    toolshare_ensure_column($pdo, 'users', 'last_blocked_at', 'DATETIME NULL AFTER `last_suspended_at`');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_admin_actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admin_id INT NULL,
            action_type VARCHAR(50) NOT NULL,
            notes TEXT NULL,
            metadata LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_admin_actions_user (user_id),
            INDEX idx_user_admin_actions_admin (admin_id),
            INDEX idx_user_admin_actions_type (action_type),
            CONSTRAINT fk_user_admin_actions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_admin_actions_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}
