<?php
// Secure file download proxy — prevents direct public access to storage/uploads
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Helpers/Security.php';
require_once __DIR__ . '/../app/Helpers/Functions.php';
require_once __DIR__ . '/../app/Auth.php';
$cfg=cfg();
Security::startSession($cfg);
Auth::requireLogin();
$file = $_GET['file'] ?? '';
// Only allow storage/uploads relative
$base = realpath(cfg('upload.dir') ?? dirname(__DIR__).'/storage/uploads');
$target = realpath($base . '/' . $file);
if (!$target || strpos($target, $base)!==0 || !file_exists($target)) {
    http_response_code(404);
    echo "File not found";
    exit;
}
$mime = mime_content_type($target) ?: 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.basename($target).'"');
header('Content-Length: '.filesize($target));
readfile($target);
