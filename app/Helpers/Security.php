<?php
class Security {
    public static function startSession(array $cfg) {
        $sessName = $cfg['security']['session_name'] ?? 'SR_SESS';
        $secure = $cfg['security']['cookie_secure'] ?? false;
        // Auto-detect if HTTPS not available: don't force secure on http localhost
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            // if not https, disable secure to allow local dev; production should be https
            if (($cfg['app']['env'] ?? 'production') === 'development') $secure = false;
            // In cli, keep as configured
            if (php_sapi_name() === 'cli') $secure = false;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_name($sessName);
            $params = [
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $cfg['security']['cookie_httponly'] ?? true,
                'samesite' => $cfg['security']['cookie_samesite'] ?? 'Lax',
            ];
            if (PHP_VERSION_ID >= 70300) session_set_cookie_params($params);
            else session_set_cookie_params($params['lifetime'], $params['path'].'; samesite='.$params['samesite'], $params['domain'], $params['secure'], $params['httponly']);
            session_start();
        }
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
    public static function csrfField(): string {
        $t = self::csrfToken();
        return '<input type="hidden" name="_csrf" value="'.htmlspecialchars($t, ENT_QUOTES, 'UTF-8').'">';
    }
    public static function verifyCsrf($token): bool {
        return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], (string)$token);
    }
    public static function e(?string $s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function sanitize(string $s): string {
        return trim(strip_tags($s));
    }
    public static function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

    // Rate limiting via DB login_attempts
    public static function checkRateLimit(PDO $pdo, string $identifier, string $ip, int $max, int $windowMinutes): bool {
        // true = allowed, false = blocked
        $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM login_attempts WHERE (identifier=? OR ip_address=?) AND attempt_time > ?");
        $stmt->execute([$identifier, $ip, $since]);
        $c = (int)$stmt->fetchColumn();
        return $c < $max;
    }
    public static function logAttempt(PDO $pdo, string $identifier, string $ip) {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (identifier, ip_address, attempt_time) VALUES (?, ?, NOW())");
        $stmt->execute([$identifier, $ip]);
        // cleanup old
        $pdo->exec("DELETE FROM login_attempts WHERE attempt_time < NOW() - INTERVAL 1 DAY");
    }
    public static function clearAttempts(PDO $pdo, string $identifier, string $ip) {
        $pdo->prepare("DELETE FROM login_attempts WHERE identifier=? OR ip_address=?")->execute([$identifier, $ip]);
    }

    public static function clientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
