<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers/Security.php';
require_once __DIR__ . '/Helpers/Functions.php';

class Auth {
    public static function attempt(string $username, string $password): array {
        $pdo = Database::pdo();
        $cfg = cfg();
        $ip = Security::clientIp();
        $max = (int)($cfg['limits']['login_attempts'] ?? 5);
        $window = (int)($cfg['limits']['login_window_minutes'] ?? 15);
        if (!Security::checkRateLimit($pdo, $username, $ip, $max, $window)) {
            return ['ok'=>false, 'msg'=>"অতিরিক্ত চেষ্টা! {$window} মিনিট পরে চেষ্টা করুন / Too many attempts. Try after {$window} minutes."];
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND is_active=1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Security::logAttempt($pdo, $username, $ip);
            return ['ok'=>false, 'msg'=>'ব্যবহারকারীর নাম বা পাসওয়ার্ড ভুল / Invalid credentials'];
        }
        Security::clearAttempts($pdo, $username, $ip);
        // session regenerate
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
        log_activity($pdo, 'login', 'auth', $user['id'], 'User logged in');
        return ['ok'=>true];
    }
    public static function check(): bool { return !empty($_SESSION['user_id']); }
    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }
    public static function logout() {
        $pdo = Database::pdo();
        if (!empty($_SESSION['user_id'])) log_activity($pdo,'logout','auth',$_SESSION['user_id'],'Logout');
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }
    public static function requireLogin() {
        if (!self::check()) { header('Location: login.php'); exit; }
    }
}
