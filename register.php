<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/email_verification_helpers.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '') {
        $error = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!toolshare_mail_is_ready()) {
        $error = "Email verification is not configured yet. Please complete the SMTP setup in config/mail.php first.";
    } else {
        $pass = password_hash($password, PASSWORD_DEFAULT);

        // Basic check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->rowCount() > 0) {
            $error = "Email already registered.";
        } else {
            try {
                $token = toolshare_generate_email_verification_token();
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        full_name,
                        email,
                        password,
                        role,
                        email_is_verified,
                        email_verification_token_hash,
                        email_verification_sent_at
                    ) VALUES (?, ?, ?, 'user', 0, ?, NOW())
                ");
                $created = $stmt->execute([$name, $email, $pass, $token['hash']]);

                if ($created) {
                    $userId = (int)$pdo->lastInsertId();
                    try {
                        toolshare_send_verification_email(
                            $email,
                            $name,
                            toolshare_build_email_verification_link($userId, $token['raw'])
                        );
                        header("Location: login.php?registered=verify&email=" . urlencode($email));
                        exit();
                    } catch (Throwable $mailError) {
                        header("Location: login.php?registered=pending_mail&email=" . urlencode($email));
                        exit();
                    }
                }
            } catch (Throwable $e) {
                $error = "Registration failed. Please try again.";
            }
            if (empty($error)) {
                $error = "Registration failed. Try again.";
            }
        }
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
    <title>Create Account | ToolShare</title>
    <style>
        /* Reusing Login Styles for Brand Consistency */
        :root { --primary: #15324a; --accent: #1f6f78; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #1a3654; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        
        .auth-card { background: white; width: 100%; max-width: 450px; padding: 40px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        .logo { text-align: center; font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-bottom: 20px; }
        
        h2 { color: var(--primary); text-align: center; margin-bottom: 8px; }
        p.subtitle { text-align: center; color: #718096; margin-bottom: 25px; font-size: 0.9rem; }

        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--primary); margin-bottom: 6px; text-transform: uppercase; }
        input { width: 100%; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--accent); }

        .btn-reg { 
            width: 100%; background: var(--accent); color: white; padding: 14px; 
            border: none; border-radius: 100px; font-weight: 800; font-size: 1rem; 
            cursor: pointer; margin-top: 10px; transition: 0.3s;
        }
        .btn-reg:hover { background: #e2a600; transform: scale(1.02); }

        .error-msg { background: #fff5f5; color: #c53030; padding: 10px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 15px; text-align: center; }
        .hint-msg { background: #eff6ff; color: #1d4ed8; padding: 10px; border-radius: 8px; font-size: 0.82rem; margin-bottom: 15px; text-align: center; }
        .footer-link { text-align: center; margin-top: 20px; font-size: 0.85rem; color: #718096; }
        .footer-link a { color: var(--primary); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="logo">TOOLSHARE</div>
    <h2>Join ToolShare</h2>
    <p class="subtitle">Start sharing and earning today.</p>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>
    <?php if (!toolshare_mail_is_ready()): ?>
        <div class="hint-msg">SMTP is not configured yet. Update <code>config/mail.php</code> before testing email verification.</div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" placeholder="John Doe" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="john@example.com" required>
        </div>
        <div class="form-group">
            <label>Create Password</label>
            <input type="password" name="password" placeholder="Min. 8 characters" required>
        </div>
        <div class="form-group">
            <label>Re-enter Password</label>
            <input type="password" name="confirm_password" placeholder="Re-enter your password" required>
        </div>
        <button type="submit" class="btn-reg">Create Account</button>
    </form>

    <div class="footer-link">
        Already have an account? <a href="login.php">Log in</a>
    </div>
</div>
</body>
</html>
