<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . toolshare_dashboard_link());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $role = toolshare_normalize_role($user['role'] ?? 'user');
    $status = $user ? toolshare_user_account_status($user) : 'active';
    if ($user && password_verify($pass, $user['password']) && in_array($role, ['ops', 'admin', 'owner_admin'], true) && $status === 'active') {
        $sessionRole = $role === 'owner_admin' ? 'admin' : null;
        toolshare_sign_in($user, $sessionRole);
        header("Location: admin_dashboard.php");
        exit;
    } elseif ($user && password_verify($pass, $user['password']) && in_array($role, ['ops', 'admin', 'owner_admin'], true) && $status !== 'active') {
        $error = toolshare_account_status_message($user);
    } else {
        $error = "Invalid operations credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Login | ToolShare</title>
    <style>
        :root { --primary: #15324a; --accent: #1f6f78; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #0f2233 0%, #15324a 100%); display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .auth-card { background: white; width: 100%; max-width: 420px; padding: 40px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.28); }
        .logo { text-align: center; font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-bottom: 12px; }
        h2 { color: var(--primary); text-align: center; margin-bottom: 8px; }
        p.subtitle { text-align: center; color: #718096; margin-bottom: 25px; font-size: 0.9rem; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--primary); margin-bottom: 6px; text-transform: uppercase; }
        input { width: 100%; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(31, 111, 120, 0.08); }
        .btn-login { width: 100%; background: var(--primary); color: white; padding: 14px; border: none; border-radius: 100px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .btn-login:hover { background: #1f3f5d; transform: translateY(-2px); }
        .error-msg { background: #fff5f5; color: #c53030; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; border: 1px solid #feb2b2; }
        .footer-link { text-align: center; margin-top: 20px; font-size: 0.88rem; color: #718096; }
        .footer-link a { color: var(--primary); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="logo">TOOLSHARE</div>
        <h2>Operations Login</h2>
        <p class="subtitle">For internal operations staff and the business owner reviewing bookings, returns, and disputes.</p>

        <?php if (isset($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!empty($_GET['msg'])): ?>
            <div class="error-msg"><?= htmlspecialchars((string)$_GET['msg']) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Sign In to Operations</button>
        </form>

        <div class="footer-link">
            Back to <a href="login.php">User Login</a>
        </div>
        <div class="footer-link">
            Business owner? <a href="owner_login.php">Owner Dashboard Login</a>
        </div>
    </div>
</body>
</html>
