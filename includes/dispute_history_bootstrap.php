<?php
require_once __DIR__ . '/returns_bootstrap.php';

if (!function_exists('toolshare_bootstrap_dispute_history')) {
    function toolshare_bootstrap_dispute_history(PDO $pdo): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS dispute_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    dispute_id INT NOT NULL,
                    admin_id INT NULL,
                    previous_status VARCHAR(40) NULL,
                    new_status VARCHAR(40) NOT NULL,
                    previous_decision VARCHAR(60) NULL,
                    new_decision VARCHAR(60) NOT NULL,
                    deposit_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    admin_notes TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_dispute_history_dispute (dispute_id),
                    INDEX idx_dispute_history_admin (admin_id),
                    CONSTRAINT fk_dispute_history_dispute FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
                    CONSTRAINT fk_dispute_history_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        } catch (Throwable $e) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS dispute_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    dispute_id INT NOT NULL,
                    admin_id INT NULL,
                    previous_status VARCHAR(40) NULL,
                    new_status VARCHAR(40) NOT NULL,
                    previous_decision VARCHAR(60) NULL,
                    new_decision VARCHAR(60) NOT NULL,
                    deposit_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    admin_notes TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_dispute_history_dispute (dispute_id),
                    INDEX idx_dispute_history_admin (admin_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        $bootstrapped = true;
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        toolshare_bootstrap_dispute_history($pdo);
    }
}
