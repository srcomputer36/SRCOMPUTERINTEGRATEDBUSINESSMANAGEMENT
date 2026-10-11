<?php
$pdo=Database::pdo();
$err=null;
$backupDir=cfg('backup.dir') ?? dirname(__DIR__,4).'/storage/backups';
if(!is_dir($backupDir)) mkdir($backupDir,0755,true);
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_backup'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    try{
      $filename='backup_'.date('Ymd_His').'.sql';
      $path=$backupDir.'/'.$filename;
      // Generate dump via PDO (not shell exec for shared host)
      $tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
      $out="-- SR Computer Backup ".date('Y-m-d H:i:s')."\nSET FOREIGN_KEY_CHECKS=0;\n";
      foreach($tables as $t){
        $create=$pdo->query("SHOW CREATE TABLE `$t`")->fetch();
        $out.="\n-- Table $t\nDROP TABLE IF EXISTS `$t`;\n".$create['Create Table'].";\n";
        $rows=$pdo->query("SELECT * FROM `$t`");
        foreach($rows as $row){
          $cols=array_map(fn($c)=>"`$c`", array_keys($row));
          $vals=array_map(fn($v)=> $v===null?'NULL':$pdo->quote($v), array_values($row));
          $out.="INSERT INTO `$t` (".implode(',',$cols).") VALUES (".implode(',',$vals).");\n";
        }
      }
      $out.="\nSET FOREIGN_KEY_CHECKS=1;\n";
      file_put_contents($path,$out);
      // gzip?
      if(function_exists('gzencode')){
        $gz=$path.'.gz';
        file_put_contents($gz,gzencode($out,9));
        unlink($path);
        $filename.='.gz';
        $size=filesize($gz);
      } else $size=filesize($path);
      $pdo->prepare("INSERT INTO backup_logs (filename,type,size,status,created_by) VALUES (?,?,?,?,?)")->execute([$filename,'manual',$size,'success',$_SESSION['user_id']??null]);
      log_activity($pdo,'backup','system',0,"Backup $filename");
      flash('success',"Backup created: $filename (".round($size/1024,1)." KB)");
      header("Location: index.php?page=backup"); exit;
    }catch(Exception $e){ $err=$e->getMessage(); $pdo->prepare("INSERT INTO backup_logs (filename,type,size,status,created_by) VALUES (?,?,?,?,?)")->execute(['error','manual',0,'failed',$_SESSION['user_id']??null]); }
  }
}
if(isset($_GET['download'])){
  $f=basename($_GET['download']);
  $path=$backupDir.'/'.$f;
  if(!file_exists($path)) die("Not found");
  // auth already required via index.php; serve
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename='.$f);
  readfile($path); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['restore']) && isset($_FILES['restore_file'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    try{
      $tmp=$_FILES['restore_file']['tmp_name'];
      $content=file_get_contents($tmp);
      if(str_ends_with($_FILES['restore_file']['name'],'.gz')) $content=gzdecode($content);
      // Very careful: execute raw SQL in transaction
      $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
      $pdo->exec($content);
      $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
      log_activity($pdo,'restore','system',0,"Restore ". $_FILES['restore_file']['name']);
      flash('success','Restore completed — verify data');
      header("Location: index.php?page=backup"); exit;
    }catch(Exception $e){ $err="Restore failed: ".$e->getMessage(); }
  }
}
$logs=$pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 20")->fetchAll();
$files=array_diff(scandir($backupDir)?:[],['.','..']);
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Backup & Restore</div>
      <div class="card-body">
        <form method="POST">
          <?= Security::csrfField() ?>
          <p class="small text-muted">Creates a full SQL dump via PDO (compatible with shared hosting without shell). Stored in <code>storage/backups/</code> protected by .htaccess.</p>
          <button name="create_backup" value="1" class="btn btn-primary w-100"><i class="bi bi-cloud-arrow-down"></i> Create Backup Now</button>
        </form>
        <hr>
        <form method="POST" enctype="multipart/form-data">
          <?= Security::csrfField() ?>
          <label class="form-label">Restore from SQL/.gz</label><input type="file" name="restore_file" class="form-control" accept=".sql,.gz" required>
          <button name="restore" value="1" class="btn btn-warning w-100 mt-2" data-confirm="Restore will overwrite current data. Continue?">Restore</button>
          <div class="small text-danger mt-1">Test restore on staging first!</div>
        </form>
      </div>
    </div>
    <div class="card mt-3">
      <div class="card-header">Backup Files (protected)</div>
      <div class="list-group list-group-flush">
        <?php foreach($files as $f): ?><a href="index.php?page=backup&download=<?= urlencode($f) ?>" class="list-group-item d-flex justify-content-between"><span><?= e($f) ?></span><span class="badge bg-light text-dark"><?= round(filesize($backupDir.'/'.$f)/1024,1) ?> KB</span></a><?php endforeach; if(!$files): ?><div class="p-3 text-muted small">No backups yet</div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Backup Logs</div>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>File</th><th>Type</th><th>Size</th><th>Status</th><th>Time</th></tr></thead><tbody>
        <?php foreach($logs as $l): ?><tr><td><?= e($l['filename']) ?></td><td><?= e($l['type']) ?></td><td><?= $l['size']? round($l['size']/1024,1).' KB':'-' ?></td><td><span class="badge bg-<?= $l['status']=='success'?'success':'danger' ?>"><?= e($l['status']) ?></span></td><td class="small"><?= bd_datetime($l['created_at']) ?></td></tr><?php endforeach; if(!$logs): ?><tr><td colspan="5" class="text-center text-muted">No logs</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
