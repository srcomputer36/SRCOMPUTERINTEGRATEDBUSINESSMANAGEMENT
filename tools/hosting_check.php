<?php
// Hosting compatibility checklist — run via browser or CLI: php tools/hosting_check.php
$configPath = __DIR__ . '/../config/config.php';
$cfg = file_exists($configPath) ? require $configPath : require __DIR__ . '/../config/config.example.php';
$checks = [];
function chk($name,$ok,$detail){ global $checks; $checks[]=['name'=>$name,'ok'=>$ok,'detail'=>$detail]; }
chk('PHP version >=8.1', version_compare(PHP_VERSION,'8.1.0','>='), PHP_VERSION);
chk('PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql')?'enabled':'missing');
chk('mbstring', extension_loaded('mbstring'), extension_loaded('mbstring')?'enabled':'missing');
chk('curl', extension_loaded('curl'), extension_loaded('curl')?'enabled':'missing');
chk('json', extension_loaded('json'), extension_loaded('json')?'enabled':'missing');
chk('fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo')?'enabled':'missing');
chk('allow_url_fopen', ini_get('allow_url_fopen'), ini_get('allow_url_fopen')?'on':'off');
chk('HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on', isset($_SERVER['HTTPS'])?$_SERVER['HTTPS']:'off (enable SSL)');
try{
  require __DIR__ . '/../app/Database.php';
  Database::config($cfg);
  $pdo=Database::pdo();
  $v=$pdo->query("SELECT VERSION()")->fetchColumn();
  chk('MySQL connection & version', true, $v);
  chk('InnoDB', true, $pdo->query("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() LIMIT 1")->fetchColumn() ?: 'check');
}catch(Exception $e){ chk('MySQL connection', false, $e->getMessage()); }
$writable=['storage','storage/uploads','storage/backups','storage/logs'];
foreach($writable as $d){
  $p=__DIR__.'/../'.$d;
  chk("Writable $d", is_writable($p) || @mkdir($p,0755,true), is_writable($p)?'writable':'not writable (chmod 755)');
}
chk(' Outbound HTTPS (Google API)', function_exists('curl_init'), 'curl needed for Drive');
if(php_sapi_name()==='cli'){
  foreach($checks as $c) echo ($c['ok']?'[OK] ':'[FAIL] ').$c['name'].' — '.$c['detail']."\n";
  exit(array_filter($checks, fn($c)=>!$c['ok']) ? 1 : 0);
}
?><!doctype html><html><head><meta charset="utf-8"><title>Hosting Check</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="p-4"><h3>Hosting Compatibility Checklist</h3><table class="table"><thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>
<?php foreach($checks as $c): ?><tr class="<?= $c['ok']?'table-success':'table-danger' ?>"><td><?= htmlspecialchars($c['name']) ?></td><td><?= $c['ok']?'OK':'FAIL' ?></td><td><?= htmlspecialchars($c['detail']) ?></td></tr><?php endforeach; ?>
</tbody></table><p class="small text-muted">Run: <code>php tools/hosting_check.php</code> | Document root should point to <code>public/</code> where possible.</p></body></html>
