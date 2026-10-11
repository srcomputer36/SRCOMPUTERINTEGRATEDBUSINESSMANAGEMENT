<?php
// Global helpers
function cfg(string $key = null) {
    static $c = null;
    if ($c === null) {
        $path = dirname(__DIR__,2) . '/config/config.php';
        $c = file_exists($path) ? require $path : require dirname(__DIR__,2) . '/config/config.example.php';
        date_default_timezone_set($c['app']['timezone'] ?? 'Asia/Dhaka');
    }
    if ($key === null) return $c;
    $parts = explode('.', $key);
    $v = $c;
    foreach ($parts as $p) $v = $v[$p] ?? null;
    return $v;
}
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function h(?string $s): string { return e($s); }

function bdt($amount): string {
    $sym = cfg('app.currency_symbol') ?? '৳';
    // if null, use ৳
    if (!$sym) $sym = '৳';
    return $sym . number_format((float)$amount, 2);
}
function bn_number($n): string {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return str_replace($en, $bn, (string)$n);
}
function bd_date(?string $d, string $fmt = null): string {
    if (!$d) return '-';
    $fmt = $fmt ?? cfg('app.date_format') ?? 'd/m/Y';
    // Handle Y-m-d or Y-m-d H:i:s
    $ts = strtotime($d);
    if ($ts === false) return e($d);
    return date($fmt, $ts);
}
function bd_datetime(?string $d): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    if ($ts === false) return e($d);
    $f = (cfg('app.date_format') ?? 'd/m/Y') . ' h:i A';
    return date($f, $ts);
}

function setting(string $key, $default = null) {
    try {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key`=?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v !== false ? $v : $default;
    } catch (Exception $e) { return $default; }
}
function set_setting(string $key, string $value) {
    $pdo = Database::pdo();
    $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)")->execute([$key,$value]);
}
function flash(string $key, $value = null) {
    if ($value !== null) { $_SESSION['_flash'][$key] = $value; return; }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}
function old(string $key, $default='') { return e($_SESSION['_old'][$key] ?? $default); }

function log_activity(PDO $pdo, string $action, string $module, $record_id = null, string $desc = '') {
    try {
        $uid = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, record_id, description, ip_address) VALUES (?,?,?,?,?,?)")
            ->execute([$uid, $action, $module, $record_id, $desc, $ip]);
    } catch (Exception $e) { error_log($e->getMessage()); }
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Generate unique tracking/invoice/txn numbers safely — nested-transaction safe (fixes sales entry bug)
function next_seq(PDO $pdo, string $key, int $pad=4): int {
    $pdo->prepare("INSERT IGNORE INTO settings (`key`, value) VALUES (?, '0')")->execute([$key]);
    $wasInTxn = $pdo->inTransaction();
    if (!$wasInTxn) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key`=? FOR UPDATE");
        $stmt->execute([$key]);
        $val = (int)$stmt->fetchColumn();
        $next = $val + 1;
        $pdo->prepare("UPDATE settings SET value=? WHERE `key`=?")->execute([(string)$next, $key]);
        if (!$wasInTxn) $pdo->commit();
        return $next;
    } catch (Exception $e) {
        if (!$wasInTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
function generate_tracking_id(PDO $pdo): string {
    try {
        $pdo->exec("INSERT IGNORE INTO settings (`key`, value) VALUES ('tracking_seq','0')");
        try { $max = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING(tracking_id, 14) AS UNSIGNED)) FROM pcc_cases WHERE tracking_id LIKE 'PCC-%'")->fetchColumn(); } catch(Exception $e){ $max=0; }
        $wasInTxn = $pdo->inTransaction();
        if (!$wasInTxn) $pdo->beginTransaction();
        // lock row
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key`='tracking_seq' FOR UPDATE");
        $stmt->execute();
        $cur = (int)$stmt->fetchColumn();
        if ($cur < $max) {
            $pdo->prepare("UPDATE settings SET value=? WHERE `key`='tracking_seq'")->execute([(string)$max]);
            $cur = $max;
        }
        $next = $cur + 1;
        $pdo->prepare("UPDATE settings SET value=? WHERE `key`='tracking_seq'")->execute([(string)$next]);
        if (!$wasInTxn) $pdo->commit();
        return 'PCC-' . date('Ymd') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        if (isset($wasInTxn) && !$wasInTxn && $pdo->inTransaction()) $pdo->rollBack();
        return 'PCC-' . date('Ymd') . '-' . str_pad(random_int(1,9999),4,'0',STR_PAD_LEFT);
    }
}
function generate_invoice_no(PDO $pdo, string $type='sale'): string {
    $key = $type === 'sale' ? 'invoice_seq_sale' : 'invoice_seq_purchase';
    $prefix = $type === 'sale' ? 'INV-S-' : 'INV-P-';
    $pdo->prepare("INSERT IGNORE INTO settings (`key`, value) VALUES (?, '0')")->execute([$key]);
    $wasInTxn = $pdo->inTransaction();
    if (!$wasInTxn) $pdo->beginTransaction();
    try {
        $s = $pdo->prepare("SELECT value FROM settings WHERE `key`=? FOR UPDATE");
        $s->execute([$key]);
        $val = (int)$s->fetchColumn();
        $next = $val + 1;
        $pdo->prepare("UPDATE settings SET value=? WHERE `key`=?")->execute([(string)$next, $key]);
        if (!$wasInTxn) $pdo->commit();
        return $prefix . date('Ymd') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        if (!$wasInTxn && $pdo->inTransaction()) $pdo->rollBack();
        return $prefix . date('Ymd') . '-' . rand(1000,9999);
    }
}
function generate_txn_no(PDO $pdo): string {
    $key='txn_seq';
    $pdo->prepare("INSERT IGNORE INTO settings (`key`, value) VALUES (?, '0')")->execute([$key]);
    $wasInTxn = $pdo->inTransaction();
    if (!$wasInTxn) $pdo->beginTransaction();
    try {
        $s=$pdo->prepare("SELECT value FROM settings WHERE `key`=? FOR UPDATE");
        $s->execute([$key]);
        $val=(int)$s->fetchColumn();
        $next=$val+1;
        $pdo->prepare("UPDATE settings SET value=? WHERE `key`=?")->execute([(string)$next,$key]);
        if (!$wasInTxn) $pdo->commit();
        return 'TXN-' . date('Ymd') . '-' . str_pad($next,4,'0',STR_PAD_LEFT);
    } catch(Exception $e){
        if (!$wasInTxn && $pdo->inTransaction()) $pdo->rollBack();
        return 'TXN-'.date('Ymd').'-'.rand(1000,9999);
    }
}
