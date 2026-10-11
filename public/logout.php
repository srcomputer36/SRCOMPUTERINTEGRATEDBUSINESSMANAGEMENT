<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Helpers/Security.php';
require_once __DIR__ . '/../app/Helpers/Functions.php';
require_once __DIR__ . '/../app/Auth.php';
$cfg=cfg();
Security::startSession($cfg);
Auth::logout();
header('Location: login.php');
exit;
