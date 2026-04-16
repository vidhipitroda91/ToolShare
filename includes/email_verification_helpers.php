<?php
require_once __DIR__ . '/email_verification_bootstrap.php';
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/auth.php';

if (!function_exists('toolshare_email_verification_required')) {
    function toolshare_email_verification_required(array $user): bool
    {
        $role = toolshare_normalize_role($user['role'] ?? 'user');
        return $role === 'user' && (int)($user['email_is_verified'] ?? 1) !== 1;
    }

    function toolshare_generate_email_verification_token(): array
    {
        $raw = bin2hex(random_bytes(32));
        return [
            'raw' => $raw,
            'hash' => hash('sha256', $raw),
        ];
    }

    function toolshare_build_email_verification_link(int $userId, string $token): string
    {
        return toolshare_mail_app_url() . '/verify_email.php?uid=' . urlencode((string)$userId) . '&token=' . urlencode($token);
    }

    function toolshare_build_email_change_link(int $userId, string $token): string
    {
        return toolshare_mail_app_url() . '/verify_email_change.php?uid=' . urlencode((string)$userId) . '&token=' . urlencode($token);
    }

    function toolshare_build_password_reset_link(int $userId, string $token): string
    {
        return toolshare_mail_app_url() . '/reset_password.php?uid=' . urlencode((string)$userId) . '&token=' . urlencode($token);
    }

    function toolshare_email_value_in_use(PDO $pdo, string $email, int $ignoreUserId = 0): bool
    {
        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE (email = ? OR pending_email = ?)
              AND id <> ?
            LIMIT 1
        ");
        $stmt->execute([$email, $email, $ignoreUserId]);
        return (bool)$stmt->fetch();
    }

    function toolshare_issue_email_verification(PDO $pdo, int $userId, string $email, string $name): void
    {
        $token = toolshare_generate_email_verification_token();
        $sentAt = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            UPDATE users
            SET email_is_verified = 0,
                email_verified_at = NULL,
                email_verification_token_hash = ?,
                email_verification_sent_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$token['hash'], $sentAt, $userId]);

        toolshare_send_verification_email($email, $name, toolshare_build_email_verification_link($userId, $token['raw']));
    }

    function toolshare_resend_email_verification(PDO $pdo, array $user): void
    {
        toolshare_issue_email_verification(
            $pdo,
            (int)$user['id'],
            (string)$user['email'],
            (string)($user['full_name'] ?? '')
        );
    }

    function toolshare_issue_email_change(PDO $pdo, array $user, string $newEmail): void
    {
        $token = toolshare_generate_email_verification_token();
        $sentAt = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE users
            SET pending_email = ?,
                email_change_token_hash = ?,
                email_change_sent_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$newEmail, $token['hash'], $sentAt, (int)$user['id']]);

        toolshare_send_email_change_verification_email(
            $newEmail,
            (string)($user['full_name'] ?? ''),
            toolshare_build_email_change_link((int)$user['id'], $token['raw'])
        );
    }

    function toolshare_verify_email(PDO $pdo, int $userId, string $token): array
    {
        $stmt = $pdo->prepare("
            SELECT id, email_is_verified, email_verification_token_hash, email_verification_sent_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['status' => 'invalid', 'message' => 'This verification link is invalid.'];
        }

        if ((int)$user['email_is_verified'] === 1) {
            return ['status' => 'already_verified', 'message' => 'Your email is already verified. You can sign in now.'];
        }

        $expectedHash = (string)($user['email_verification_token_hash'] ?? '');
        if ($expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $token))) {
            return ['status' => 'invalid', 'message' => 'This verification link is invalid or has been replaced.'];
        }

        $sentAtRaw = trim((string)($user['email_verification_sent_at'] ?? ''));
        $sentAt = $sentAtRaw !== '' ? strtotime($sentAtRaw) : false;
        $expiresAt = $sentAt !== false ? $sentAt + (toolshare_email_verification_expiry_hours() * 3600) : null;
        if ($expiresAt !== null && time() > $expiresAt) {
            return ['status' => 'expired', 'message' => 'This verification link has expired. Please request a new one.'];
        }

        $update = $pdo->prepare("
            UPDATE users
            SET email_is_verified = 1,
                email_verified_at = NOW(),
                email_verification_token_hash = NULL,
                email_verification_sent_at = NULL
            WHERE id = ?
        ");
        $update->execute([$userId]);

        return ['status' => 'verified', 'message' => 'Your email has been verified successfully. You can sign in now.'];
    }

    function toolshare_verify_email_change(PDO $pdo, int $userId, string $token): array
    {
        $stmt = $pdo->prepare("
            SELECT id, pending_email, email_change_token_hash, email_change_sent_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['status' => 'invalid', 'message' => 'This email change link is invalid.'];
        }

        $pendingEmail = trim((string)($user['pending_email'] ?? ''));
        $expectedHash = (string)($user['email_change_token_hash'] ?? '');
        if ($pendingEmail === '' || $expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $token))) {
            return ['status' => 'invalid', 'message' => 'This email change link is invalid or has already been used.'];
        }

        $sentAtRaw = trim((string)($user['email_change_sent_at'] ?? ''));
        $sentAt = $sentAtRaw !== '' ? strtotime($sentAtRaw) : false;
        $expiresAt = $sentAt !== false ? $sentAt + (toolshare_email_verification_expiry_hours() * 3600) : null;
        if ($expiresAt !== null && time() > $expiresAt) {
            return ['status' => 'expired', 'message' => 'This email change link has expired. Please request a new change from your profile.'];
        }

        if (toolshare_email_value_in_use($pdo, $pendingEmail, (int)$user['id'])) {
            return ['status' => 'invalid', 'message' => 'That email address is already being used by another account.'];
        }

        $update = $pdo->prepare("
            UPDATE users
            SET email = ?,
                email_is_verified = 1,
                email_verified_at = NOW(),
                pending_email = NULL,
                email_change_token_hash = NULL,
                email_change_sent_at = NULL
            WHERE id = ?
        ");
        $update->execute([$pendingEmail, $userId]);

        return ['status' => 'verified', 'message' => 'Your email address has been updated successfully.'];
    }

    function toolshare_issue_password_reset(PDO $pdo, array $user): void
    {
        $token = toolshare_generate_email_verification_token();
        $sentAt = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE users
            SET password_reset_token_hash = ?,
                password_reset_sent_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$token['hash'], $sentAt, (int)$user['id']]);

        toolshare_send_password_reset_email(
            (string)$user['email'],
            (string)($user['full_name'] ?? ''),
            toolshare_build_password_reset_link((int)$user['id'], $token['raw'])
        );
    }

    function toolshare_validate_password_reset(PDO $pdo, int $userId, string $token): array
    {
        $stmt = $pdo->prepare("
            SELECT id, password_reset_token_hash, password_reset_sent_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['status' => 'invalid', 'message' => 'This password reset link is invalid.'];
        }

        $expectedHash = (string)($user['password_reset_token_hash'] ?? '');
        if ($expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $token))) {
            return ['status' => 'invalid', 'message' => 'This password reset link is invalid or has already been used.'];
        }

        $sentAtRaw = trim((string)($user['password_reset_sent_at'] ?? ''));
        $sentAt = $sentAtRaw !== '' ? strtotime($sentAtRaw) : false;
        $expiresAt = $sentAt !== false ? $sentAt + (toolshare_password_reset_expiry_hours() * 3600) : null;
        if ($expiresAt !== null && time() > $expiresAt) {
            return ['status' => 'expired', 'message' => 'This password reset link has expired. Please request a new one.'];
        }

        return ['status' => 'valid', 'message' => 'Password reset link is valid.'];
    }

    function toolshare_complete_password_reset(PDO $pdo, int $userId, string $token, string $newPassword): array
    {
        $validation = toolshare_validate_password_reset($pdo, $userId, $token);
        if (($validation['status'] ?? '') !== 'valid') {
            return $validation;
        }

        $update = $pdo->prepare("
            UPDATE users
            SET password = ?,
                password_reset_token_hash = NULL,
                password_reset_sent_at = NULL
            WHERE id = ?
        ");
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        return ['status' => 'reset', 'message' => 'Your password has been updated successfully. You can sign in now.'];
    }
}
