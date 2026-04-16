<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/email_verification_helpers.php';

$error = '';
$success = '';
$email = trim((string)($_GET['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!toolshare_mail_is_ready()) {
        $error = 'Mail delivery is not configured yet. Please complete the SMTP setup first.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            try {
                toolshare_issue_password_reset($pdo, $user);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        if ($error === '') {
            $success = 'If an account exists for that email, a password reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | ToolShare</title>
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
        input { width: 100%; min-height: 50px; padding: 0 16px; border: 1px solid #dbe7f1; border-radius: 14px; font-size: 15px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border-radius: 999px; text-decoration: none; font-weight: 700; border: none; cursor: pointer; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-secondary { border: 1px solid #dbe7f1; color: var(--primary); background: #fff; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Forgot Password</h1>
        <p>Enter your email address and we will send you a link to reset your ToolShare password.</p>
        <?php if ($error !== ''): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success !== ''): ?>
            <div class="msg success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Send Reset Link</button>
                <a class="btn btn-secondary" href="login.php">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>
