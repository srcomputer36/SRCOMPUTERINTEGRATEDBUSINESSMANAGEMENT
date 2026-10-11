<?php
// SR Computer — Config Template
// Copy to config.php and fill real values. Never commit config.php with secrets.

return [
    'app' => [
        'name' => 'SR Computer',
        'env' => 'production', // production | development
        'url' => 'https://yourdomain.com', // no trailing slash
        'timezone' => 'Asia/Dhaka',
        'debug' => false,
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'srcomputer',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_name' => 'SR_SESS',
        'csrf_token_name' => '_csrf',
        // Set secure true when HTTPS enabled
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],
    'upload' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_mimes' => ['image/jpeg','image/png','image/webp','application/pdf'],
        'dir' => dirname(__DIR__) . '/storage/uploads',
    ],
    'backup' => [
        'dir' => dirname(__DIR__) . '/storage/backups',
    ],
    'google' => [
        // Leave empty to use local storage. Fill for Drive API.
        'drive_folder_id' => '',
        'sheets_id' => '',
        'credentials_path' => dirname(__DIR__) . '/storage/credentials/service-account.json',
        'mode' => 'local', // local | drive
    ],
    'limits' => [
        'login_attempts' => 5,
        'login_window_minutes' => 15,
    ],
];
