<?php
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Helpers/Security.php';
require_once __DIR__ . '/../app/Helpers/Functions.php';
require_once __DIR__ . '/../app/Auth.php';
$cfg = cfg();
Security::startSession($cfg);
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD']==='POST'){
    if (!Security::verifyCsrf($_POST['_csrf'] ?? '')) {
        $error = 'CSRF token invalid. Refresh and try again.';
    } else {
        $res = Auth::attempt(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
        if ($res['ok']) { header('Location: index.php'); exit; }
        else $error = $res['msg'];
    }
}
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= e($cfg['app']['name'] ?? 'SR Computer') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600&family=Inter:wght@500;700&display=swap" rel="stylesheet">
<style>
body{background:#0f172a; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Noto Sans Bengali','Inter',sans-serif;}
.login-card{background:#fff; border-radius:16px; padding:32px; width:100%; max-width:420px; box-shadow:0 20px 40px rgba(0,0,0,.3);}
</style>
</head>
<body>
<div class="login-card">
  <div class="text-center mb-4">
    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:24px"><i class="bi bi-building"></i></div>
    <h4 class="mt-3 fw-bold">SR Computer</h4>
    <p class="text-muted small">Integrated Business Management System</p>
  </div>
  <?php if($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
  <form method="POST" autocomplete="off">
    <?= Security::csrfField() ?>
    <div class="mb-3">
      <label class="form-label">ব্যবহারকারীর নাম / Username</label>
      <input name="username" class="form-control form-control-lg" required autofocus placeholder="admin">
    </div>
    <div class="mb-3">
      <label class="form-label">পাসওয়ার্ড / Password</label>
      <input name="password" type="password" class="form-control form-control-lg" required placeholder="••••••••">
    </div>
    <button class="btn btn-primary w-100 btn-lg" type="submit"><i class="bi bi-box-arrow-in-right"></i> Login</button>
  </form>
  <div class="text-center mt-3 small text-muted">
    Default: admin / admin123 — প্রথম লগইনে পাসওয়ার্ড পরিবর্তন করুন
  </div>
  <div class="text-center mt-2 small">
    <span class="text-muted">Secure • CSRF protected • Rate limited</span>
  </div>
</div>
</body>
</html>
