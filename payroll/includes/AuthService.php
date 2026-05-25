<?php
// ============================================================
// BiT Payroll — AuthService
// Encapsulates all authentication business logic:
//   - Login (brute-force protection, credential check, session)
//   - Logout (audit log, session destruction)
// ============================================================

class AuthService
{
    // Maximum failed attempts before IP lockout
    private const MAX_ATTEMPTS  = 5;

    // Lockout window in minutes
    private const LOCK_MINUTES  = 15;

    // Alert admins after this many failures within 10 minutes
    private const ALERT_AFTER   = 3;
    private const ALERT_WINDOW  = 10;

    // Role → dashboard path map
    private const ROLE_REDIRECTS = [
        'admin'    => '../admin/dashboard.php',
        'hr'       => '../hr/dashboard.php',
        'finance'  => '../finance/dashboard.php',
        'employee' => '../employee/dashboard.php',
    ];

    private PDO    $pdo;
    private string $ip;

    // ── Constructor ─────────────────────────────────────────
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    // ============================================================
    // PUBLIC — attempt login
    // Returns an array:
    //   ['success' => true,  'redirect' => '../admin/dashboard.php']
    //   ['success' => false, 'error'    => 'Human-readable message']
    // ============================================================
    public function attempt(string $username, string $password): array
    {
        // ── Basic input validation ───────────────────────────
        if ($username === '' || $password === '') {
            return $this->fail('Please enter both username and password.');
        }

        if (strlen($username) > 60 || strlen($password) > 200) {
            return $this->fail('Invalid credentials.');
        }

        // ── Brute-force lockout check ────────────────────────
        if ($this->isLockedOut()) {
            return $this->fail(
                'Too many failed login attempts. '
                . 'Please wait ' . self::LOCK_MINUTES . ' minutes before trying again.'
            );
        }

        // ── Fetch user record ────────────────────────────────
        $user = $this->findUser($username);

        // ── Verify credentials ───────────────────────────────
        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            return $this->handleSuccess($user);
        }

        return $this->handleFailure($user, $username);
    }

    // ============================================================
    // PUBLIC — log out the current session
    // Safe to call even if session data is missing.
    // ============================================================
    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->writeAuditLog(
                (int)$_SESSION['user_id'],
                $_SESSION['username'] ?? '',
                $_SESSION['role']     ?? '',
                'Logout',
                'User logged out',
                'success'
            );
        }

        $this->destroySession();
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    // ── Check if the current IP is locked out ───────────────
    private function isLockedOut(): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM audit_logs
            WHERE  status     = 'failed'
            AND    action     = 'Login'
            AND    ip_address = ?
            AND    logged_at  > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$this->ip, self::LOCK_MINUTES]);

        return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    // ── Count recent failures from this IP ──────────────────
    private function recentFailCount(int $withinMinutes): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM audit_logs
            WHERE  status     = 'failed'
            AND    action     = 'Login'
            AND    ip_address = ?
            AND    logged_at  > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$this->ip, $withinMinutes]);

        return (int)$stmt->fetchColumn();
    }

    // ── Fetch a user row by username ─────────────────────────
    private function findUser(string $username): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT user_id, username, password, role, full_name, is_active, profile_photo
            FROM   users
            WHERE  username = ?
            LIMIT  1
        ");
        $stmt->execute([$username]);

        return $stmt->fetch();
    }

    // ── Handle a successful credential check ────────────────
    private function handleSuccess(array $user): array
    {
        // Prevent session fixation
        session_regenerate_id(true);

        // Populate session
        $_SESSION['user_id']       = $user['user_id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['name']          = $user['full_name'];
        $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
        $_SESSION['last_activity'] = time();

        // Stamp last login
        $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
                  ->execute([$user['user_id']]);

        // Audit log
        $this->writeAuditLog(
            (int)$user['user_id'],
            $user['username'],
            $user['role'],
            'Login',
            'Successful login',
            'success'
        );

        $redirect = self::ROLE_REDIRECTS[$user['role']] ?? '../auth/login.php';

        return ['success' => true, 'redirect' => $redirect];
    }

    // ── Handle a failed credential check ────────────────────
    private function handleFailure(array|false $user, string $username): array
    {
        // Audit log — known vs unknown user
        if ($user) {
            $this->writeAuditLog(
                (int)$user['user_id'],
                $user['username'],
                $user['role'],
                'Login',
                'Failed login attempt',
                'failed'
            );
        } else {
            $this->writeAuditLogAnonymous($username, 'Unknown username attempt');
        }

        // Alert admins after ALERT_AFTER failures from this IP
        $this->maybeAlertAdmins($username);

        // Build a helpful error message
        $error = $this->buildErrorMessage($user);

        return $this->fail($error);
    }

    // ── Notify admins if failure threshold is crossed ───────
    private function maybeAlertAdmins(string $username): void
    {
        try {
            if ($this->recentFailCount(self::ALERT_WINDOW) >= self::ALERT_AFTER) {
                notify_role(
                    $this->pdo,
                    'admin',
                    '⚠️ Multiple Failed Login Attempts',
                    "IP {$this->ip} has failed to login " . self::ALERT_AFTER
                        . "+ times in the last " . self::ALERT_WINDOW
                        . " minutes. Username tried: {$username}",
                    'danger'
                );
            }
        } catch (Exception $e) {
            // Non-critical — silently ignore
        }
    }

    // ── Build a user-facing error message ───────────────────
    private function buildErrorMessage(array|false $user): string
    {
        if ($user && !$user['is_active']) {
            return 'Your account has been deactivated. Contact the administrator.';
        }

        // How many attempts remain before lockout?
        $used      = $this->recentFailCount(self::LOCK_MINUTES);
        $remaining = max(0, self::MAX_ATTEMPTS - $used);

        if ($remaining > 0) {
            return "Invalid username or password. {$remaining} attempt(s) remaining before lockout.";
        }

        return 'Invalid credentials.';
    }

    // ── Write a full audit log row ───────────────────────────
    private function writeAuditLog(
        int    $userId,
        string $username,
        string $role,
        string $action,
        string $details,
        string $status
    ): void {
        try {
            $this->pdo->prepare("
                INSERT INTO audit_logs
                    (user_id, username, role, action, details, ip_address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$userId, $username, $role, $action, $details, $this->ip, $status]);
        } catch (Exception $e) {
            error_log('Audit log write failed: ' . $e->getMessage());
        }
    }

    // ── Write an audit log row for an unknown username ───────
    private function writeAuditLogAnonymous(string $username, string $details): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO audit_logs
                    (username, action, details, ip_address, status)
                VALUES (?, 'Login', ?, ?, 'failed')
            ")->execute([$username, $details, $this->ip]);
        } catch (Exception $e) {
            error_log('Audit log write failed: ' . $e->getMessage());
        }
    }

    // ── Completely destroy the current session ───────────────
    private function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    // ── Helper: build a failure result array ────────────────
    private function fail(string $message): array
    {
        return ['success' => false, 'error' => $message];
    }
}
