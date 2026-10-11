<?php
$pdo=Database::pdo();
$module=$_GET['module']??'';
$where=''; $vals=[];
if($module){ $where="WHERE module=?"; $vals=[$module]; }
$rows=$pdo->prepare("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id $where ORDER BY l.created_at DESC LIMIT 200");
$rows->execute($vals); $rows=$rows->fetchAll();
$mods=$pdo->query("SELECT DISTINCT module FROM activity_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="filter-bar">
  <form method="GET" class="d-flex gap-2"><input type="hidden" name="page" value="activity"><select name="module" class="form-select" style="max-width:200px"><option value="">All modules</option><?php foreach($mods as $m): ?><option <?= $module==$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach; ?></select><button class="btn btn-primary">Filter</button></form>
</div>
<div class="card">
  <div class="card-header">Activity Log (last 200)</div>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th><th>IP</th></tr></thead><tbody>
    <?php foreach($rows as $r): ?><tr><td class="small"><?= bd_datetime($r['created_at']) ?></td><td><?= e($r['username']?:'system') ?></td><td><span class="badge bg-secondary"><?= e($r['action']) ?></span></td><td><?= e($r['module']) ?></td><td><?= e($r['record_id']) ?></td><td class="small"><?= e($r['description']) ?></td><td class="small"><?= e($r['ip_address']) ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center text-muted">No logs</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
