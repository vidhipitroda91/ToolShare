<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/email_verification_helpers.php';

$message = 'This email change link is invalid.';
$status = 'invalid';
$uid = (int)($_GET['uid'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

if ($uid > 0 && $token !== '') {
    $result = toolshare_verify_email_change($pdo, $uid, $token);
    $status = (string)$result['status'];
    $message = (string)$result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email Change | ToolShare</title>
    <style>
        :root { --primary: #15324a; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #eef4f8 0%, #f8fafc 100%); }
        .card { width: min(520px, calc(100% - 24px)); background: #fff; border-radius: 28px; padding: 36px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12); }
        h1 { margin: 0 0 10px; color: var(--primary); }
        p { color: #64748b; line-height: 1.6; }
        .msg { margin: 18px 0 24px; padding: 14px 16px; border-radius: 16px; font-size: 14px; }
        .msg.success { background: #ecfdf5; color: #166534; border: 1px solid #a7f3d0; }
        .msg.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border-radius: 999px; text-decoration: none; font-weight: 700; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { border: 1px solid #dbe7f1; color: var(--primary); }
    </style>
</head>
<body>
    <div class="card">
        <h1>Email Change Confirmation</h1>
        <p>Confirm your new ToolShare email address here.</p>
        <div class="msg <?= $status === 'verified' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
        <div class="actions">
            <a class="btn btn-primary" href="profile.php">Go to Profile</a>
            <a class="btn btn-secondary" href="login.php">Go to Login</a>
        </div>
    </div>
</body>
</html>
