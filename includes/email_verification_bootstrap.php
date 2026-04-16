<?php
require_once __DIR__ . '/returns_bootstrap.php';

if (!function_exists('toolshare_bootstrap_email_verification')) {
    function toolshare_bootstrap_email_verification(PDO $pdo): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        toolshare_ensure_column($pdo, 'users', 'email_is_verified', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `password`');
        toolshare_ensure_column($pdo, 'users', 'email_verified_at', 'DATETIME NULL AFTER `email_is_verified`');
        toolshare_ensure_column($pdo, 'users', 'email_verification_token_hash', 'CHAR(64) NULL AFTER `email_verified_at`');
        toolshare_ensure_column($pdo, 'users', 'email_verification_sent_at', 'DATETIME NULL AFTER `email_verification_token_hash`');
        toolshare_ensure_column($pdo, 'users', 'pending_email', 'VARCHAR(255) NULL AFTER `email_verification_sent_at`');
        toolshare_ensure_column($pdo, 'users', 'email_change_token_hash', 'CHAR(64) NULL AFTER `pending_email`');
        toolshare_ensure_column($pdo, 'users', 'email_change_sent_at', 'DATETIME NULL AFTER `email_change_token_hash`');
        toolshare_ensure_column($pdo, 'users', 'password_reset_token_hash', 'CHAR(64) NULL AFTER `email_change_sent_at`');
        toolshare_ensure_column($pdo, 'users', 'password_reset_sent_at', 'DATETIME NULL AFTER `password_reset_token_hash`');

        $pdo->exec("
            UPDATE users
            SET email_verified_at = COALESCE(email_verified_at, NOW())
            WHERE email_is_verified = 1
              AND email_verified_at IS NULL
        ");

        $bootstrapped = true;
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        toolshare_bootstrap_email_verification($pdo);
    }
}
