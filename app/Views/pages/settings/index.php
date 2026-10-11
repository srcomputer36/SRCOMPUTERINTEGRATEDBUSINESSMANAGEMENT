<?php
$pdo=Database::pdo();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    $keys=['company_name','company_address','company_phone','company_email','default_govt_fee','default_service_charge','default_other_fee','fee_mode','status_strict','drive_folder_id','sheets_id','drive_mode','currency','date_format','tracking_prefix'];
    foreach($keys as $k){
      if(isset($_POST[$k])) set_setting($k, trim($_POST[$k]));
    }
    // password change if provided
    if(!empty($_POST['new_password'])){
      if(strlen($_POST['new_password'])<6) $err="Password too short";
      else {
        $hash=password_hash($_POST['new_password'],PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
        $err=null;
      }
    }
    if(!$err){ flash('success','Settings saved'); header("Location: index.php?page=settings"); exit; }
  }
}
$settings=[];
foreach($pdo->query("SELECT * FROM settings")->fetchAll() as $r) $settings[$r['key']]=$r['value'];
function s($k,$d=''){ global $settings; return e($settings[$k]??$d); }
?>
<?php if($err): ?><div class="alert alert-warning"><?= e($err) ?></div><?php endif; ?>
<form method="POST" class="row g-3">
  <?= Security::csrfField() ?>
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">Company & General</div>
      <div class="card-body row g-2">
        <div class="col-md-6"><label class="form-label">Company Name</label><input name="company_name" class="form-control" value="<?= s('company_name','SR Computer') ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input name="company_phone" class="form-control" value="<?= s('company_phone') ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input name="company_email" class="form-control" value="<?= s('company_email') ?>"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input name="company_address" class="form-control" value="<?= s('company_address') ?>"></div>
        <div class="col-md-3"><label class="form-label">Currency</label><input name="currency" class="form-control" value="<?= s('currency','BDT') ?>"></div>
        <div class="col-md-3"><label class="form-label">Date Format</label><select name="date_format" class="form-select"><option <?= s('date_format')=='d/m/Y'?'selected':'' ?>>d/m/Y</option><option <?= s('date_format')=='d-m-Y'?'selected':'' ?>>d-m-Y</option><option <?= s('date_format')=='Y-m-d'?'selected':'' ?>>Y-m-d</option></select></div>
        <div class="col-md-3"><label class="form-label">Tracking Prefix</label><input name="tracking_prefix" class="form-control" value="<?= s('tracking_prefix','PCC-') ?>"></div>
        <div class="col-md-3"><label class="form-label">Status Strict</label><select name="status_strict" class="form-select"><option value="0" <?= s('status_strict')=='0'?'selected':'' ?>>Permissive (allow skip)</option><option value="1" <?= s('status_strict')=='1'?'selected':'' ?>>Strict (sequential)</option></select></div>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header">PCC Fees Defaults</div>
      <div class="card-body row g-2">
        <div class="col-md-3"><label class="form-label">Govt Fee</label><input name="default_govt_fee" type="number" step="0.01" class="form-control" value="<?= s('default_govt_fee','500') ?>"></div>
        <div class="col-md-3"><label class="form-label">Service Charge</label><input name="default_service_charge" type="number" step="0.01" class="form-control" value="<?= s('default_service_charge','1500') ?>"></div>
        <div class="col-md-3"><label class="form-label">Other Fee</label><input name="default_other_fee" type="number" step="0.01" class="form-control" value="<?= s('default_other_fee','0') ?>"></div>
        <div class="col-md-3"><label class="form-label">Fee Mode</label><select name="fee_mode" class="form-select"><option value="service_only" <?= s('fee_mode')=='service_only'?'selected':'' ?>>Service only = revenue</option><option value="inclusive" <?= s('fee_mode')=='inclusive'?'selected':'' ?>>Inclusive (sum all)</option></select></div>
        <div class="col-12 small text-muted">Service only mode follows Prompt §8: only service_charge is revenue; govt/other are direct costs.</div>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header">Google Integration</div>
      <div class="card-body row g-2">
        <div class="col-md-6"><label class="form-label">Drive Folder ID (masked)</label><input name="drive_folder_id" class="form-control" value="<?= s('drive_folder_id') ?>" placeholder="1A2B..."></div>
        <div class="col-md-6"><label class="form-label">Sheets ID (masked)</label><input name="sheets_id" class="form-control" value="<?= s('sheets_id') ?>" placeholder="1A2B..."></div>
        <div class="col-md-4"><label class="form-label">Drive Mode</label><select name="drive_mode" class="form-select"><option value="local" <?= s('drive_mode')=='local'?'selected':'' ?>>Local (recommended for shared host)</option><option value="drive" <?= s('drive_mode')=='drive'?'selected':'' ?>>Drive API</option></select></div>
        <div class="col-md-8 small text-muted d-flex align-items-end">Credentials JSON path: <code>storage/credentials/service-account.json</code> — never commit. Scope: drive.file only.</div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Security</div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label">New Password (leave blank to keep)</label><input name="new_password" type="password" class="form-control" placeholder="••••••••"></div>
        <div class="alert alert-light border small">Login rate limiting: 5 attempts / 15 min per IP. Session regenerates on login. Cookies: httponly, samesite=Lax, secure when HTTPS.</div>
        <button name="save_settings" value="1" class="btn btn-primary w-100">Save Settings</button>
      </div>
    </div>
    <div class="card mt-3"><div class="card-header">Hosting Checklist</div><div class="card-body small"><a href="tools/hosting_check.php" target="_blank" class="btn btn-sm btn-outline-secondary">Run hosting_check.php</a><div class="mt-2">PHP: <?= PHP_VERSION ?> • DB: MySQL via PDO • HTTPS: <?= isset($_SERVER['HTTPS'])?'yes':'unknown' ?></div></div></div>
  </div>
</form>
