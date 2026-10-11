<?php
// Database singleton — PDO with prepared statements
class Database {
    private static $pdo = null;
    private static $config = null;

    public static function config(array $cfg = null) {
        if ($cfg !== null) self::$config = $cfg;
        return self::$config;
    }

    public static function pdo(): PDO {
        if (self::$pdo !== null) return self::$pdo;
        $cfg = self::$config;
        if (!$cfg) {
            $path = dirname(__DIR__) . '/config/config.php';
            if (file_exists($path)) $cfg = require $path;
            else $cfg = require dirname(__DIR__) . '/config/config.example.php';
            self::$config = $cfg;
        }
        $db = $cfg['db'];
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'], $db['port'] ?? 3306, $db['database'], $db['charset'] ?? 'utf8mb4');
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        self::$pdo = new PDO($dsn, $db['username'], $db['password'], $opts);
        // Set timezone for session?
        try { self::$pdo->exec("SET time_zone = '+06:00'"); } catch (Exception $e) {}
        return self::$pdo;
    }

    public static function reset() { self::$pdo = null; }
}
