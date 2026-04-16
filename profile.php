<?php
session_start();
require 'config/db.php';
require 'includes/auth.php';
require 'includes/site_chrome.php';
require 'includes/email_verification_helpers.php';

toolshare_require_signed_in();

$user_id = (int)$_SESSION['user_id'];
$current_role = toolshare_current_role();
$personal_error = '';
$personal_info = '';
$password_error = '';

function toolshare_profile_fetch_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT id, full_name, email, password, pending_email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user = toolshare_profile_fetch_user($pdo, $user_id);

    if (!$user) {
        toolshare_sign_out();
        header('Location: ' . toolshare_login_link_for_role($current_role) . '?msg=session_expired');
        exit;
    }

    if ($action === 'save_profile') {
        $full_name = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($full_name === '' || $email === '') {
            $personal_error = 'Full name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $personal_error = 'Please enter a valid email address.';
        } else {
            if (toolshare_email_value_in_use($pdo, $email, $user_id)) {
                $personal_error = 'That email address is already being used by another account.';
            } else {
                $update = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                $update->execute([$full_name, $user_id]);

                $_SESSION['user_name'] = $full_name;
                if (strcasecmp($email, (string)$user['email']) !== 0) {
                    try {
                        $user['full_name'] = $full_name;
                        toolshare_issue_email_change($pdo, $user, $email);
                        header('Location: profile.php?msg=email_change_sent#personal-details');
                        exit;
                    } catch (Throwable $e) {
                        $personal_error = $e->getMessage();
                    }
                } else {
                    header('Location: profile.php?msg=profile_saved#personal-details');
                    exit;
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = (string)($_POST['current_password'] ?? '');
        $new_password = (string)($_POST['new_password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $password_error = 'Please fill in all password fields.';
        } elseif (!password_verify($current_password, (string)$user['password'])) {
            $password_error = 'Your current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $password_error = 'Your new password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $password_error = 'New password and re-entered password do not match.';
        } elseif (password_verify($new_password, (string)$user['password'])) {
            $password_error = 'Please choose a new password that is different from your current one.';
        } else {
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);
            header('Location: profile.php?msg=password_saved#password-security');
            exit;
        }
    }
}

$user = toolshare_profile_fetch_user($pdo, $user_id);
if (!$user) {
    toolshare_sign_out();
    header('Location: ' . toolshare_login_link_for_role($current_role) . '?msg=session_expired');
    exit;
}

$full_name = (string)$user['full_name'];
$email = (string)$user['email'];
$pending_email = trim((string)($user['pending_email'] ?? ''));
$initial = strtoupper(substr($full_name !== '' ? $full_name : 'U', 0, 1));
$success_message = $_GET['msg'] ?? '';
$role_labels = [
    'user' => 'View Profile',
    'owner_admin' => 'Owner Profile',
    'ops' => 'Operations Profile',
    'admin' => 'Admin Profile',
];
$hero_labels = [
    'user' => 'Private account settings',
    'owner_admin' => 'Owner account settings',
    'ops' => 'Operations account settings',
    'admin' => 'Admin account settings',
];
$intro_titles = [
    'user' => 'Accounts Center',
    'owner_admin' => 'Owner Account',
    'ops' => 'Operations Account',
    'admin' => 'Admin Account',
];
$intro_descriptions = [
    'user' => 'Manage your personal details and password in one private place. This page is only visible to you.',
    'owner_admin' => 'Manage the owner dashboard account details used for business reporting, oversight, and sign-in security.',
    'ops' => 'Manage the operations account details used for internal reviews, disputes, and booking oversight.',
    'admin' => 'Manage the platform admin account details used for internal operations and sign-in security.',
];
$hero_descriptions = [
    'user' => 'Keep your ToolShare account details up to date, review your contact information, and manage your sign-in security from here.',
    'owner_admin' => 'Keep the owner account details current so internal dashboards, receipts, and account access stay in sync.',
    'ops' => 'Keep the operations account details current so internal notifications, reviews, and sign-in access stay in sync.',
    'admin' => 'Keep the admin account details current so platform controls, internal reviews, and sign-in access stay in sync.',
];
$profile_label = $role_labels[$current_role] ?? 'View Profile';
$hero_label = $hero_labels[$current_role] ?? 'Private account settings';
$intro_title = $intro_titles[$current_role] ?? 'Account Profile';
$intro_description = $intro_descriptions[$current_role] ?? 'Manage your account details and password in one private place.';
$hero_description = $hero_descriptions[$current_role] ?? 'Manage your ToolShare account details and sign-in security from here.';
$back_href = toolshare_dashboard_link();
$back_label = in_array($current_role, ['ops', 'admin'], true)
    ? 'Back to Operations'
    : ($current_role === 'owner_admin' ? 'Back to Owner Dashboard' : 'Back to Dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile | ToolShare</title>
    <?php toolshare_render_chrome_assets(); ?>
    <style>
        :root {
            --profile-navy: #15324a;
            --profile-blue: #0f4c81;
            --profile-surface: rgba(255, 255, 255, 0.9);
            --profile-border: #dbe7f1;
            --profile-text: #1f3348;
            --profile-muted: #64748b;
            --profile-success-bg: #ecfdf5;
            --profile-success-text: #166534;
            --profile-error-bg: #fef2f2;
            --profile-error-text: #b91c1c;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #eef4f8 0%, #f7fafc 100%);
            color: var(--profile-text);
            font-family: 'Inter', sans-serif;
        }
        .profile-topbar {
            width: min(1240px, 94%);
            margin: 0 auto;
            padding: 18px 0 0;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .profile-back-link,
        .profile-home-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 800;
            border: 1px solid #dbe7f1;
            background: rgba(255, 255, 255, 0.8);
            color: var(--profile-navy);
        }
        .profile-page {
            width: min(1240px, 94%);
            margin: 0 auto;
            padding: 18px 0 56px;
        }
        .profile-layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 26px;
            align-items: start;
        }
        .profile-sidebar {
            position: sticky;
            top: 104px;
            background: var(--profile-surface);
            border: 1px solid rgba(255, 255, 255, 0.78);
            border-radius: 28px;
            padding: 24px;
            box-shadow: 0 20px 36px rgba(15, 35, 56, 0.08);
            backdrop-filter: blur(14px);
        }
        .profile-sidebar-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #e0f2fe;
            color: var(--profile-blue);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .profile-sidebar-label::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }
        .profile-sidebar h1 {
            margin: 0 0 8px;
            font-size: 2rem;
            line-height: 1.05;
            color: var(--profile-navy);
        }
        .profile-sidebar p {
            margin: 0 0 22px;
            color: var(--profile-muted);
            font-size: 14px;
            line-height: 1.55;
        }
        .profile-nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 22px;
        }
        .profile-nav a {
            display: block;
            text-decoration: none;
            color: var(--profile-navy);
            font-weight: 700;
            background: #f8fbfd;
            border: 1px solid var(--profile-border);
            border-radius: 18px;
            padding: 14px 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .profile-nav a:hover {
            background: #eef7fc;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(15, 76, 129, 0.08);
        }
        .profile-sidebar-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            border-radius: 999px;
            padding: 13px 16px;
            background: #fff4f4;
            color: #b91c1c;
            font-weight: 800;
            border: 1px solid #fecaca;
        }
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }
        .profile-hero,
        .profile-card {
            background: var(--profile-surface);
            border: 1px solid rgba(255, 255, 255, 0.78);
            border-radius: 30px;
            box-shadow: 0 20px 36px rgba(15, 35, 56, 0.08);
            backdrop-filter: blur(14px);
        }
        .profile-hero {
            overflow: hidden;
        }
        .profile-hero-top {
            height: 64px;
            background:
                radial-gradient(circle at top right, rgba(31, 111, 120, 0.18), transparent 38%),
                linear-gradient(135deg, #f0f8ff 0%, #eef6fb 55%, #f8fbfd 100%);
        }
        .profile-hero-body {
            padding: 0 28px 28px;
            margin-top: -26px;
            display: flex;
            align-items: flex-end;
            gap: 18px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: linear-gradient(135deg, #15324a 0%, #1f6f78 100%);
            border: 4px solid #ffffff;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(21, 50, 74, 0.2);
        }
        .profile-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #ebf5fb;
            color: var(--profile-blue);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .profile-eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }
        .profile-hero-copy h2 {
            margin: 0 0 6px;
            font-size: 2rem;
            color: var(--profile-navy);
        }
        .profile-hero-copy p {
            margin: 0;
            color: var(--profile-muted);
            line-height: 1.6;
        }
        .profile-card {
            padding: 28px;
        }
        .profile-card-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .profile-card-header h3 {
            margin: 0 0 6px;
            font-size: 1.35rem;
            color: var(--profile-navy);
        }
        .profile-card-header p {
            margin: 0;
            color: var(--profile-muted);
            font-size: 14px;
            line-height: 1.55;
            max-width: 640px;
        }
        .profile-badge {
            padding: 9px 12px;
            border-radius: 999px;
            background: #eef7fc;
            color: var(--profile-blue);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .profile-alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
            border: 1px solid transparent;
        }
        .profile-alert-success {
            background: var(--profile-success-bg);
            color: var(--profile-success-text);
            border-color: #a7f3d0;
        }
        .profile-alert-error {
            background: var(--profile-error-bg);
            color: var(--profile-error-text);
            border-color: #fecaca;
        }
        .profile-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .profile-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .profile-field-full {
            grid-column: 1 / -1;
        }
        .profile-field label {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--profile-navy);
        }
        .profile-field input {
            width: 100%;
            min-height: 50px;
            padding: 0 16px;
            border-radius: 16px;
            border: 1px solid var(--profile-border);
            background: #ffffff;
            color: var(--profile-text);
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }
        .profile-field input:focus {
            border-color: #8fc3df;
            box-shadow: 0 0 0 4px rgba(143, 195, 223, 0.18);
        }
        .profile-field small {
            color: var(--profile-muted);
            font-size: 12px;
            line-height: 1.5;
        }
        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }
        .profile-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 18px;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-weight: 800;
            font-size: 14px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .profile-btn:hover {
            transform: translateY(-1px);
        }
        .profile-btn-primary {
            background: linear-gradient(135deg, #15324a 0%, #1f6f78 100%);
            color: #ffffff;
            box-shadow: 0 14px 24px rgba(21, 50, 74, 0.16);
        }
        .profile-btn-secondary {
            background: #f8fbfd;
            color: var(--profile-navy);
            border: 1px solid var(--profile-border);
        }
        .profile-footer-actions {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            padding-top: 8px;
        }
        .profile-footer-actions p {
            margin: 0;
            color: var(--profile-muted);
            font-size: 14px;
        }
        .profile-inline-link {
            color: var(--profile-blue);
            text-decoration: none;
            font-weight: 800;
        }
        .profile-inline-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 980px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            .profile-sidebar {
                position: static;
            }
        }
        @media (max-width: 720px) {
            .profile-page {
                width: min(100% - 20px, 100%);
                padding-bottom: 42px;
            }
            .profile-card,
            .profile-sidebar {
                padding: 22px;
                border-radius: 24px;
            }
            .profile-hero-body {
                padding: 0 22px 22px;
            }
            .profile-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php if ($current_role === 'user'): ?>
    <?php toolshare_render_nav(['support_href' => 'index.php#support']); ?>
<?php else: ?>
    <div class="profile-topbar">
        <a class="profile-back-link" href="<?= htmlspecialchars($back_href) ?>"><?= htmlspecialchars($back_label) ?></a>
        <a class="profile-home-link" href="logout.php">Log out</a>
    </div>
<?php endif; ?>

<div class="profile-page">
    <div class="profile-layout">
        <aside class="profile-sidebar">
            <span class="profile-sidebar-label"><?= htmlspecialchars($profile_label) ?></span>
            <h1><?= htmlspecialchars($intro_title) ?></h1>
            <p><?= htmlspecialchars($intro_description) ?></p>

            <nav class="profile-nav" aria-label="Profile sections">
                <a href="#personal-details">Personal details</a>
                <a href="#password-security">Password &amp; security</a>
                <a href="#account-actions">Account actions</a>
            </nav>

            <a class="profile-sidebar-logout" href="logout.php">Log out</a>
        </aside>

        <main class="profile-main">
            <section class="profile-hero">
                <div class="profile-hero-top"></div>
                <div class="profile-hero-body">
                    <div class="profile-avatar"><?= htmlspecialchars($initial) ?></div>
                    <div class="profile-hero-copy">
                        <span class="profile-eyebrow"><?= htmlspecialchars($hero_label) ?></span>
                        <h2><?= htmlspecialchars($full_name) ?></h2>
                        <p><?= htmlspecialchars($hero_description) ?></p>
                    </div>
                </div>
            </section>

            <section id="personal-details" class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <h3>Personal details</h3>
                        <p>These details help you manage bookings, receipts, and communication inside ToolShare.</p>
                    </div>
                    <span class="profile-badge">Only visible to you</span>
                </div>

                <?php if ($success_message === 'profile_saved'): ?>
                    <div class="profile-alert profile-alert-success">Your personal details were updated successfully.</div>
                <?php elseif ($success_message === 'email_change_sent'): ?>
                    <div class="profile-alert profile-alert-success">Your profile was updated. Please check your new email address to confirm the email change.</div>
                <?php elseif ($personal_error !== ''): ?>
                    <div class="profile-alert profile-alert-error"><?= htmlspecialchars($personal_error) ?></div>
                <?php endif; ?>
                <?php if ($pending_email !== ''): ?>
                    <div class="profile-alert profile-alert-success">Pending email change: <?= htmlspecialchars($pending_email) ?>. Your current login email will stay active until you verify the new one.</div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="save_profile">
                    <div class="profile-form-grid">
                        <div class="profile-field profile-field-full">
                            <label for="full_name">Full name</label>
                            <input id="full_name" type="text" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required>
                        </div>

                        <div class="profile-field">
                            <label for="email">Email address</label>
                            <input id="email" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button type="submit" class="profile-btn profile-btn-primary">Save personal details</button>
                    </div>
                </form>
            </section>

            <section id="password-security" class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <h3>Password &amp; security</h3>
                        <p>Change your password here. Use your current password first, then enter and re-enter the new one.</p>
                    </div>
                    <span class="profile-badge">Private sign-in details</span>
                </div>

                <?php if ($success_message === 'password_saved'): ?>
                    <div class="profile-alert profile-alert-success">Your password was updated successfully.</div>
                <?php elseif ($password_error !== ''): ?>
                    <div class="profile-alert profile-alert-error"><?= htmlspecialchars($password_error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="profile-form-grid">
                        <div class="profile-field profile-field-full">
                            <label for="current_password">Current password</label>
                            <input id="current_password" type="password" name="current_password" required>
                        </div>

                        <div class="profile-field">
                            <label for="new_password">New password</label>
                            <input id="new_password" type="password" name="new_password" required>
                            <small>Use at least 8 characters.</small>
                        </div>

                        <div class="profile-field">
                            <label for="confirm_password">Re-enter new password</label>
                            <input id="confirm_password" type="password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button type="submit" class="profile-btn profile-btn-primary">Update password</button>
                        <a href="forgot_password.php?email=<?= urlencode($email) ?>" class="profile-btn profile-btn-secondary">Forgot password</a>
                    </div>
                </form>
            </section>

            <section id="account-actions" class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <h3>Account actions</h3>
                        <p>When you are done reviewing your details, you can safely sign out from here.</p>
                    </div>
                </div>

                <div class="profile-footer-actions">
                    <p>Your session stays private to this account until you log out.</p>
                    <a href="logout.php" class="profile-btn profile-btn-secondary">Log out</a>
                </div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
