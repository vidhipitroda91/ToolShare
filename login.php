<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/email_verification_helpers.php';

$info = '';
$unverifiedEmail = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        $role = toolshare_normalize_role($user['role'] ?? 'user');
        $status = toolshare_user_account_status($user);
        if ($status !== 'active') {
            $error = toolshare_account_status_message($user);
        } elseif (toolshare_email_verification_required($user)) {
            $error = "Please verify your email before signing in.";
            $unverifiedEmail = $email;
        } elseif ($role === 'admin') {
            $error = "Admin accounts must use the admin login page.";
        } else {
            $sessionRole = $role === 'owner_admin' ? 'user' : null;
            toolshare_sign_in($user, $sessionRole);
            header("Location: " . toolshare_dashboard_link());
            exit();
        }
    } else {
        $error = "Invalid email or password.";
    }
}

if (isset($_SESSION['user_id'])) {
    header("Location: " . toolshare_dashboard_link());
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ToolShare</title>
    <style>
        :root { --primary: #15324a; --accent: #1f6f78; --text: #2d3748; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body { 
            background: linear-gradient(135deg, #1a3654 0%, #0f172a 100%); 
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
        }

        .auth-card { 
            background: white; width: 100%; max-width: 400px; padding: 40px; 
            border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
        }

        .logo { text-align: center; font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-bottom: 30px; letter-spacing: -1px; }
        
        h2 { color: var(--primary); font-size: 1.5rem; margin-bottom: 10px; }
        p.subtitle { color: #718096; font-size: 0.9rem; margin-bottom: 25px; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; text-transform: uppercase; }
        
        input { 
            width: 100%; padding: 14px; border: 1.5px solid #e2e8f0; border-radius: 12px; 
            outline: none; transition: 0.3s; font-size: 1rem;
        }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(26, 54, 84, 0.1); }

        .btn-login { 
            width: 100%; background: var(--primary); color: white; padding: 14px; 
            border: none; border-radius: 100px; font-weight: 700; font-size: 1rem; 
            cursor: pointer; transition: 0.3s; margin-top: 10px;
        }
        .btn-login:hover { background: #2c5282; transform: translateY(-2px); }

        .error-msg { background: #fff5f5; color: #c53030; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; border: 1px solid #feb2b2; }
        .info-msg { background: #eff6ff; color: #1d4ed8; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; border: 1px solid #bfdbfe; }
        
        .footer-link { text-align: center; margin-top: 25px; font-size: 0.9rem; color: #718096; }
        .footer-link a { color: var(--primary); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="logo">TOOLSHARE</div>
    <h2>Welcome back</h2>
    <p class="subtitle">Please enter your details to sign in.</p>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?= htmlspecialchars((string)$error) ?></div>
        <?php if ($unverifiedEmail !== ''): ?>
            <div class="info-msg">
                Need a new verification email?
                <a href="resend_verification.php?email=<?= urlencode($unverifiedEmail) ?>" style="color:inherit; font-weight:700;">Resend verification</a>
            </div>
        <?php endif; ?>
    <?php elseif (!empty($_GET['registered']) && $_GET['registered'] === 'verify'): ?>
        <div class="info-msg">
            Account created. Please check your inbox to verify your email before signing in.
            <?php if (!empty($_GET['email'])): ?>
                <a href="resend_verification.php?email=<?= urlencode((string)$_GET['email']) ?>" style="color:inherit; font-weight:700;">Resend verification</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($_GET['registered']) && $_GET['registered'] === 'pending_mail'): ?>
        <div class="info-msg">
            Your account was created, but the verification email could not be sent at that moment.
            <?php if (!empty($_GET['email'])): ?>
                <a href="resend_verification.php?email=<?= urlencode((string)$_GET['email']) ?>" style="color:inherit; font-weight:700;">Send verification now</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($_GET['msg'])): ?>
        <div class="error-msg"><?= htmlspecialchars((string)$_GET['msg']) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="e.g. alex@gmail.com" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">Sign In</button>
    </form>

    <div class="footer-link">
        Don't have an account? <a href="register.php">Sign up</a>
    </div>
    <div class="footer-link" style="margin-top: 8px; font-size: 0.82rem;">
        Forgot your password? <a href="forgot_password.php">Reset it</a>
    </div>
    <div class="footer-link" style="margin-top: 8px; font-size: 0.82rem;">
        Are you an admin? <a href="admin_login.php">Admin Login</a>
    </div>
</div>
</body>
</html>
