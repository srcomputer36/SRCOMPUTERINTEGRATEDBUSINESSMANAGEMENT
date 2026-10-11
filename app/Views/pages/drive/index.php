<?php
$pdo=Database::pdo();
$files=$pdo->query("SELECT f.*, c.tracking_id, c.applicant_name FROM case_files f JOIN pcc_cases c ON c.id=f.case_id ORDER BY f.uploaded_at DESC LIMIT 100")->fetchAll();
$mode=setting('drive_mode','local');
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center"><span>Google Drive / Files Integration</span><span class="badge bg-<?= $mode=='drive'?'success':'secondary' ?>"><?= e($mode) ?> mode</span></div>
  <div class="card-body">
    <div class="alert alert-info small">
      <strong>Mode: <?= e($mode) ?></strong> — Current implementation uses local <code>storage/uploads/</code> (secure, auth-protected). To enable Google Drive, set <code>drive_mode=drive</code> in Settings and add <code>storage/credentials/service-account.json</code> with <code>drive.file</code> scope. Folder ID: <code><?= e(setting('drive_folder_id','—') ? substr(setting('drive_folder_id','—'),0,12).'...' : '—') ?></code> (masked).
    </div>
    <p class="small text-muted">All PCC attachments are listed below. Direct URLs are proxy-protected — unauthenticated access blocked via .htaccess.</p>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>File</th><th>Case</th><th>Uploaded</th><th>Link</th></tr></thead><tbody>
      <?php foreach($files as $f): ?><tr><td><?= e($f['file_name']) ?> <br><small class="text-muted"><?= round(($f['size']??0)/1024,1) ?> KB • <?= e($f['mime_type']) ?></small></td><td><a href="index.php?page=pcc_view&id=<?= (int)$f['case_id'] ?>"><?= e($f['tracking_id']) ?></a><br><small><?= e($f['applicant_name']) ?></small></td><td class="small"><?= bd_datetime($f['uploaded_at']) ?></td><td><a href="<?= e($f['drive_link']?:$f['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Open</a></td></tr><?php endforeach; if(!$files): ?><tr><td colspan="4" class="text-center text-muted">No files yet</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
