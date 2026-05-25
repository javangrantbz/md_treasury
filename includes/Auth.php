<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Totp.php';
require_once __DIR__ . '/AuditLog.php';

final class Auth
{
    private const MAX_ATTEMPTS   = 5;
    private const LOCKOUT_MINUTES = 15;
    private const SESSION_IDLE_TIMEOUT = 900; // 15 minutes
    
    // IP Brute Force Limits
    private const IP_MAX_ATTEMPTS = 20;
    private const IP_WINDOW_MINUTES = 10;
    private const IP_BLOCK_MINUTES = 60;

    public static function attemptLogin(PDO $pdo, string $login, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 1. Check if IP is blocked
        $ipCheck = self::checkIpBlock($pdo, $ip);
        if (!$ipCheck['allowed']) {
            return ['success' => false, 'message' => $ipCheck['message']];
        }

        $sql = "
            SELECT
                u.*,
                GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ',') AS role_codes,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS role_names,
                d.name AS department_name
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE (u.username = :username OR u.email = :email)
            GROUP BY u.id
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $login, 'email' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return same message whether user exists or not (prevents enumeration)
        if (!$user) {
            // Even if user doesn't exist, we track the IP attempt to prevent discovery by mass guessing
            self::recordFailedAttempt($pdo, 0, $ip);
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if ($user['status'] === 'locked') {
            $lockedUntil = $user['locked_until'] ?? null;
            if ($lockedUntil && strtotime($lockedUntil) > time()) {
                $remaining = (int) ceil((strtotime($lockedUntil) - time()) / 60);
                return ['success' => false, 'message' => "Account locked. Try again in {$remaining} minute(s)."];
            }
            // Lock period expired — clear it
            $pdo->prepare("UPDATE users SET status='active', failed_login_count=0, locked_until=NULL WHERE id=:id")
                ->execute(['id' => $user['id']]);
            $user['status'] = 'active';
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'This account is not active. Contact support.'];
        }

        if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($pdo, (int) $user['id'], $ip);
            try {
                AuditLog::log($pdo, 'login_fail', 'user', (int)$user['id'], 'Failed login attempt for: ' . $login, (int)$user['id'], null, null, 'SECURITY');
            } catch (Throwable $e) {}
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        // Successful password — reset failure counters
        $pdo->prepare("UPDATE users SET failed_login_count=0, locked_until=NULL WHERE id=:id")
            ->execute(['id' => $user['id']]);
        
        // Clear IP attempts on success
        $pdo->prepare("DELETE FROM ip_login_attempts WHERE ip_address = :ip")
            ->execute(['ip' => $ip]);

        $_SESSION['pending_user_id']     = (int) $user['id'];
        $_SESSION['pending_mfa_verified'] = false;

        if ((int) $user['mfa_enabled'] === 1) {
            $totpConfirmed = !empty($user['mfa_totp_secret']) && !empty($user['mfa_totp_confirmed_at']);
            $redirect = $totpConfirmed
                ? url('views/auth/verify-mfa.php')
                : url('views/auth/mfa-setup.php');

            return ['success' => true, 'requires_mfa' => true, 'redirect' => $redirect];
        }

        self::completeLogin($pdo, (int) $user['id']);

        return ['success' => true, 'requires_mfa' => false, 'redirect' => url('views/portal/index.php')];
    }

    private static function recordFailedAttempt(PDO $pdo, int $userId, string $ip): void
    {
        // Per-user lockout (only if user exists)
        if ($userId > 0) {
            $pdo->prepare("UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = :id")
                ->execute(['id' => $userId]);

            $stmt = $pdo->prepare("SELECT failed_login_count FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $count = (int) ($stmt->fetchColumn() ?? 0);

            if ($count >= self::MAX_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
                $pdo->prepare("UPDATE users SET status='locked', locked_until=:locked_until WHERE id=:id")
                    ->execute(['locked_until' => $lockedUntil, 'id' => $userId]);
            }
        }

        // Per-IP lockout (always track)
        // We use a sliding window reset: if the last attempt was outside the window, we restart count at 1
        $pdo->prepare("
            INSERT INTO ip_login_attempts (ip_address, failed_count, last_attempt)
            VALUES (:ip, 1, NOW())
            ON DUPLICATE KEY UPDATE
                failed_count = IF(last_attempt < DATE_SUB(NOW(), INTERVAL :window MINUTE), 1, failed_count + 1),
                last_attempt = NOW()
        ")->execute([
            'ip' => $ip,
            'window' => self::IP_WINDOW_MINUTES
        ]);

        $stmt = $pdo->prepare("SELECT failed_count FROM ip_login_attempts WHERE ip_address = :ip");
        $stmt->execute(['ip' => $ip]);
        $ipCount = (int)$stmt->fetchColumn();

        if ($ipCount >= self::IP_MAX_ATTEMPTS) {
            $blockedUntil = date('Y-m-d H:i:s', time() + self::IP_BLOCK_MINUTES * 60);
            $pdo->prepare("UPDATE ip_login_attempts SET blocked_until = :blocked_until WHERE ip_address = :ip")
                ->execute(['blocked_until' => $blockedUntil, 'ip' => $ip]);
            
            try {
                AuditLog::log($pdo, 'ip_blocked', 'system', null, "IP address $ip blocked due to multiple failed logins.", null, null, null, 'SECURITY');
            } catch (Throwable $e) {}
        }
    }

    private static function checkIpBlock(PDO $pdo, string $ip): array
    {
        $stmt = $pdo->prepare("SELECT blocked_until FROM ip_login_attempts WHERE ip_address = :ip");
        $stmt->execute(['ip' => $ip]);
        $blockedUntil = $stmt->fetchColumn();

        if ($blockedUntil && strtotime($blockedUntil) > time()) {
            $remaining = (int) ceil((strtotime($blockedUntil) - time()) / 60);
            return [
                'allowed' => false, 
                'message' => "Too many failed attempts from your location. Please try again in {$remaining} minute(s)."
            ];
        }

        return ['allowed' => true];
    }

    public static function completeLogin(PDO $pdo, int $userId): void
    {
        $sql = "
            SELECT
                u.*,
                GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ',') AS role_codes,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS role_names,
                d.name AS department_name
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = :id
            GROUP BY u.id
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'uuid' => $user['uuid'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'department_id' => $user['department_id'] !== null ? (int) $user['department_id'] : null,
            'department_name' => $user['department_name'] ?? null,
            'branch_id' => $user['branch_id'] !== null ? (int) $user['branch_id'] : null,
            'sub_treasury_id' => $user['sub_treasury_id'] !== null ? (int) $user['sub_treasury_id'] : null,
            'register_id' => $user['register_id'] !== null ? (int) $user['register_id'] : null,
            'status' => $user['status'],
            'user_type' => $user['user_type'],
            'auth_source' => $user['auth_source'],
            'roles' => !empty($user['role_codes']) ? explode(',', $user['role_codes']) : [],
            'role_names' => !empty($user['role_names']) ? explode(',', $user['role_names']) : [],
            'last_login_at' => $user['last_login_at'] ?? null,
        ];

        unset($_SESSION['pending_user_id'], $_SESSION['pending_mfa_verified']);

        $update = $pdo->prepare("UPDATE users SET last_login_at = NOW(), current_session_id = :sid WHERE id = :id");
        $update->execute(['id' => $userId, 'sid' => session_id()]);

        try {
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            AuditLog::log($pdo, 'login', 'user', $userId, 'User logged in: ' . ($name ?: ($user['username'] ?? '')), $userId, null, null, 'SECURITY');
        } catch (Throwable $e) {
            // Silently ignore if audit_logs table does not exist yet
        }

        // Initialize session activity tracker
        $_SESSION['last_activity'] = time();
    }

    public static function verifyMfa(PDO $pdo, string $code): array
    {
        $pendingUserId = $_SESSION['pending_user_id'] ?? null;
        if (!$pendingUserId) {
            return ['success' => false, 'message' => 'Login session expired. Please sign in again.'];
        }

        $stmt = $pdo->prepare("SELECT mfa_totp_secret, mfa_totp_confirmed_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $pendingUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['mfa_totp_secret']) || empty($user['mfa_totp_confirmed_at'])) {
            return ['success' => false, 'message' => 'MFA not configured. Please complete setup first.'];
        }

        if (!Totp::verify($user['mfa_totp_secret'], $code)) {
            return ['success' => false, 'message' => 'Invalid code. Check your authenticator app and try again.'];
        }

        self::completeLogin($pdo, (int) $pendingUserId);

        return ['success' => true, 'redirect' => url('views/portal/index.php')];
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            if (function_exists('isApiRequest') && isApiRequest()) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
            }
            redirect(url('/views/auth/government-login.php'));
        }

        $user = self::user();

        // 1. Session Idle Timeout check
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > self::SESSION_IDLE_TIMEOUT)) {
            self::logout();
            $msg = 'Your session has expired due to inactivity. Please sign in again.';
            if (function_exists('isApiRequest') && isApiRequest()) {
                jsonResponse(['success' => false, 'message' => $msg, 'timeout' => true], 401);
            }
            flash('flash_error', $msg);
            redirect(url('/views/auth/government-login.php'));
        }
        $_SESSION['last_activity'] = time();

        // 2. Concurrent Session & MFA Enforcement (Requires DB check)
        // To avoid excessive DB hits, we could cache this for 30s, 
        // but for "Government Grade" we check on every sensitive request.
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT current_session_id, mfa_totp_confirmed_at, status FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $user['id']]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dbUser || $dbUser['status'] !== 'active') {
                self::logout();
                redirect(url('/views/auth/government-login.php'));
            }

            // Concurrent Session Control
            if ($dbUser['current_session_id'] !== session_id()) {
                self::logout();
                flash('flash_error', 'Your account was logged in from another location.');
                redirect(url('/views/auth/government-login.php'));
            }

            // Mandatory MFA Enforcement for Admin & Cashier
            $restrictedRoles = ['admin', 'cashier', 'auditor'];
            $userRoles = $user['roles'] ?? [];
            $hasRestrictedRole = !empty(array_intersect($restrictedRoles, $userRoles));

            if ($hasRestrictedRole && empty($dbUser['mfa_totp_confirmed_at'])) {
                // If on MFA setup page, let them through, otherwise force them there
                $currentPath = $_SERVER['REQUEST_URI'] ?? '';
                if (strpos($currentPath, 'mfa-setup.php') === false && strpos($currentPath, 'mfa-enroll.php') === false && strpos($currentPath, 'logout.php') === false) {
                    redirect(url('/views/auth/mfa-setup.php'));
                }
            }
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function fullName(): string
    {
        $user = self::user();
        return $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '';
    }
}