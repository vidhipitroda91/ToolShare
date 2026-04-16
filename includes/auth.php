<?php
if (isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/user_moderation_bootstrap.php';
    require_once __DIR__ . '/email_verification_bootstrap.php';
}

if (!function_exists('toolshare_current_role')) {
    function toolshare_normalize_role(?string $role): string
    {
        $role = strtolower(trim((string)$role));
        $allowed = ['user', 'ops', 'owner_admin', 'admin'];
        return in_array($role, $allowed, true) ? $role : 'user';
    }

    function toolshare_current_role(): string
    {
        return toolshare_normalize_role($_SESSION['user_role'] ?? 'user');
    }

    function toolshare_sign_in(array $user, ?string $sessionRole = null): void
    {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = toolshare_normalize_role($sessionRole ?? ($user['role'] ?? 'user'));
        $_SESSION['user_actual_role'] = toolshare_normalize_role($user['role'] ?? 'user');
    }

    function toolshare_actual_role(): string
    {
        return toolshare_normalize_role($_SESSION['user_actual_role'] ?? $_SESSION['user_role'] ?? 'user');
    }

    function toolshare_user_account_status(array $user): string
    {
        $status = strtolower(trim((string)($user['account_status'] ?? 'active')));
        return in_array($status, ['active', 'suspended', 'blocked'], true) ? $status : 'active';
    }

    function toolshare_account_status_message(array $user): string
    {
        $status = toolshare_user_account_status($user);
        $reason = trim((string)($user['status_reason'] ?? ''));

        if ($status === 'suspended') {
            return $reason !== '' ? "This account is suspended. {$reason}" : 'This account is suspended. Please contact support.';
        }

        if ($status === 'blocked') {
            return $reason !== '' ? "This account is blocked. {$reason}" : 'This account is blocked. Please contact support.';
        }

        return '';
    }

    function toolshare_fetch_session_user(PDO $pdo, int $userId): ?array
    {
        static $supportsAccountStatus = null;

        if ($supportsAccountStatus === null) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'account_status'");
                $supportsAccountStatus = (bool)$stmt->fetch();
            } catch (Throwable $e) {
                $supportsAccountStatus = false;
            }
        }

        $sql = "SELECT id, full_name, role";
        if ($supportsAccountStatus) {
            $sql .= ", account_status, status_reason";
        }
        $sql .= " FROM users WHERE id = ? LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    function toolshare_enforce_session_account_state(): void
    {
        if (!isset($_SESSION['user_id']) || !isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
            return;
        }

        $role = toolshare_current_role();
        $loginTarget = $role === 'user'
            ? 'login.php'
            : (in_array($role, ['ops', 'admin'], true) ? 'admin_login.php' : 'owner_login.php');

        $user = toolshare_fetch_session_user($GLOBALS['pdo'], (int)$_SESSION['user_id']);
        if (!$user) {
            toolshare_sign_out();
            header("Location: {$loginTarget}?msg=session_expired");
            exit;
        }

        $status = toolshare_user_account_status($user);
        if ($status !== 'active') {
            toolshare_sign_out();
            header("Location: {$loginTarget}?msg=" . urlencode(toolshare_account_status_message($user)));
            exit;
        }
    }

    function toolshare_sign_out(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    function toolshare_require_user(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }

        toolshare_enforce_session_account_state();

        if (toolshare_current_role() !== 'user') {
            header("Location: " . toolshare_internal_home_link());
            exit;
        }
    }

    function toolshare_login_link_for_role(?string $role = null): string
    {
        $role = $role !== null ? toolshare_normalize_role($role) : toolshare_current_role();
        if (in_array($role, ['ops', 'admin'], true)) {
            return 'admin_login.php';
        }
        if ($role === 'owner_admin') {
            return 'owner_login.php';
        }
        return 'login.php';
    }

    function toolshare_require_signed_in(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . toolshare_login_link_for_role());
            exit;
        }

        toolshare_enforce_session_account_state();
    }

    function toolshare_require_admin(): void
    {
        toolshare_require_ops();
    }

    function toolshare_require_ops(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . toolshare_login_link_for_role('ops'));
            exit;
        }

        toolshare_enforce_session_account_state();

        if (!in_array(toolshare_current_role(), ['ops', 'admin'], true)) {
            header("Location: dashboard.php");
            exit;
        }
    }

    function toolshare_require_owner_admin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . toolshare_login_link_for_role('owner_admin'));
            exit;
        }

        toolshare_enforce_session_account_state();

        if (!in_array(toolshare_current_role(), ['owner_admin', 'admin'], true)) {
            header("Location: dashboard.php");
            exit;
        }
    }

    function toolshare_internal_home_link(): string
    {
        $role = toolshare_current_role();
        if (in_array($role, ['owner_admin'], true)) {
            return 'owner_dashboard.php';
        }
        if (in_array($role, ['ops', 'admin'], true)) {
            return 'admin_dashboard.php';
        }
        return 'dashboard.php';
    }

    function toolshare_dashboard_link(): string
    {
        return toolshare_current_role() === 'user' ? 'index.php' : toolshare_internal_home_link();
    }
}
