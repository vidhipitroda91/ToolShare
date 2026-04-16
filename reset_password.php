<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/email_verification_helpers.php';

$uid = (int)($_GET['uid'] ?? $_POST['uid'] ?? 0);
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$status = 'invalid';
$message = 'This password reset link is invalid.';
$error = '';

if ($uid > 0 && $token !== '') {
    $validation = toolshare_validate_password_reset($pdo, $uid, $token);
    $status = (string)$validation['status'];
    $message = (string)$validation['message'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uid > 0 && $token !== '') {
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Please fill in both password fields.';
        $status = 'valid';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Your new password must be at least 8 characters.';
        $status = 'valid';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and re-entered password do not match.';
        $status = 'valid';
    } else {
        $result = toolshare_complete_password_reset($pdo, $uid, $token, $newPassword);
        $status = (string)$result['status'];
        $message = (string)$result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | ToolShare</title>
    <style>
        :root { --primary: #15324a; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #eef4f8 0%, #f8fafc 100%); }
        .card { width: min(520px, calc(100% - 24px)); background: #fff; border-radius: 28px; padding: 36px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12); }
        h1 { margin: 0 0 10px; color: var(--primary); }
        p { color: #64748b; line-height: 1.6; }
        .msg { margin: 18px 0; padding: 14px 16px; border-radius: 16px; font-size: 14px; }
        .msg.success { background: #ecfdf5; color: #166534; border: 1px solid #a7f3d0; }
        .msg.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--primary); font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        input { width: 100%; min-height: 50px; padding: 0 16px; border: 1px solid #dbe7f1; border-radius: 14px; font-size: 15px; margin-bottom: 16px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border-radius: 999px; text-decoration: none; font-weight: 700; border: none; cursor: pointer; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { border: 1px solid #dbe7f1; color: var(--primary); background: #fff; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Reset Password</h1>
        <p>Verify the reset link and choose a new password for your ToolShare account.</p>
        <?php if ($error !== ''): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($status === 'reset'): ?>
            <div class="msg success"><?= htmlspecialchars($message) ?></div>
        <?php elseif ($status !== 'valid'): ?>
            <div class="msg error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($status === 'valid' || $error !== ''): ?>
            <form method="POST">
                <input type="hidden" name="uid" value="<?= (int)$uid ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label for="new_password">New password</label>
                <input id="new_password" type="password" name="new_password" required>
                <label for="confirm_password">Re-enter new password</label>
                <input id="confirm_password" type="password" name="confirm_password" required>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                    <a class="btn btn-secondary" href="login.php">Back to Login</a>
                </div>
            </form>
        <?php else: ?>
            <div class="actions">
                <a class="btn btn-primary" href="login.php">Go to Login</a>
                <a class="btn btn-secondary" href="forgot_password.php">Request New Reset Link</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
