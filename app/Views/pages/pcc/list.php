<?php
$pdo=Database::pdo();
$svc=new PccService($pdo);
$pageNo=max(1,(int)($_GET['p']??1));
$filters=[
  'search'=>trim($_GET['search']??''),
  'status'=>$_GET['status']??'',
  'district'=>$_GET['district']??'',
  'from'=>$_GET['from']??'',
  'to'=>$_GET['to']??'',
  'sort'=>$_GET['sort']??'received_date',
  'dir'=>$_GET['dir']??'desc',
];
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_status'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF invalid');
  else {
    $ids=$_POST['ids']??[];
    $new=$_POST['bulk_status']??'';
    if($ids && $new){
      $res=$svc->bulkUpdateStatus($ids,$new);
      flash('success',"Bulk update: {$res['success']} success, ".count($res['failed'])." failed");
      if($res['failed']) flash('error', json_encode($res['failed']));
    }
  }
  header("Location: index.php?page=pcc_list&".http_build_query($filters));
  exit;
}
$data=$svc->list($filters,$pageNo,20);
$rows=$data['rows'];
$statuses=['','Receive','SS Printed','Sign by SP/DC','MOFA Verification','Ready for Delivery','Delivered'];
?>
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="page" value="pcc_list">
    <div class="col-md-3"><label class="form-label small">Search</label><input name="search" class="form-control" placeholder="Tracking / Name / Passport / Mobile" value="<?= e($filters['search']) ?>"></div>
    <div class="col-md-2"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All</option><?php foreach(array_slice($statuses,1) as $s): ?><option <?= $filters['status']==$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" class="form-control" value="<?= e($filters['from']) ?>"></div>
    <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" class="form-control" value="<?= e($filters['to']) ?>"></div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
      <a href="index.php?page=pcc_list" class="btn btn-outline-secondary">Reset</a>
      <a href="index.php?page=pcc_receive" class="btn btn-success ms-auto"><i class="bi bi-plus-lg"></i> New</a>
    </div>
  </form>
</div>

<form method="POST" id="bulkForm">
<?= Security::csrfField() ?>
<div id="bulkBar" class="alert alert-info d-none d-flex justify-content-between align-items-center py-2">
  <span><span class="count">0</span> selected</span>
  <span class="d-flex gap-2 align-items-center">
    <select name="bulk_status" class="form-select form-select-sm" style="width:auto"><option value="">— Change Status —</option><?php foreach(array_slice($statuses,1) as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-primary" data-confirm="Bulk status update?">Apply</button>
  </span>
</div>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Records (<?= (int)$data['total'] ?>)</span>
    <span class="small text-muted">Page <?= (int)$data['page'] ?>/<?= (int)$data['pages'] ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th><input type="checkbox" id="checkAll"></th>
          <th><a href="?page=pcc_list&sort=tracking_id&dir=<?= $filters['dir']=='asc'?'desc':'asc' ?>&<?= http_build_query(array_merge($filters,['sort'=>'tracking_id'])) ?>">Tracking</a></th>
          <th>Applicant</th>
          <th>Passport</th>
          <th>Mobile</th>
          <th>District</th>
          <th><a href="?page=pcc_list&sort=status&dir=<?= $filters['dir']=='asc'?'desc':'asc' ?>&<?= http_build_query(array_merge($filters,['sort'=>'status'])) ?>">Status</a></th>
          <th class="text-end">Total</th>
          <th class="text-end">Due</th>
          <th><a href="?page=pcc_list&sort=received_date&dir=<?= $filters['dir']=='asc'?'desc':'asc' ?>&<?= http_build_query(array_merge($filters,['sort'=>'received_date'])) ?>">Date</a></th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
          <td><a href="index.php?page=pcc_view&id=<?= (int)$r['id'] ?>" class="fw-semibold"><?= e($r['tracking_id']) ?></a></td>
          <td><?= e($r['applicant_name']) ?><?php if($r['applicant_name_bn']): ?><br><small class="text-muted"><?= e($r['applicant_name_bn']) ?></small><?php endif; ?></td>
          <td><?= e($r['passport_no']?:'—') ?></td>
          <td><?= e($r['mobile']) ?></td>
          <td><?= e($r['district']?:'—') ?></td>
          <td><span class="badge badge-status status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td class="text-end"><?= bdt($r['total_fee']) ?></td>
          <td class="text-end <?php if((float)$r['due_amount']>0) echo 'text-danger fw-bold' ?>"><?= bdt($r['due_amount']) ?></td>
          <td class="small"><?= bd_date($r['received_date']) ?></td>
          <td><a href="index.php?page=pcc_view&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a> <a href="index.php?page=pcc_edit&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a></td>
        </tr>
        <?php endforeach; if(!$rows): ?><tr><td colspan="11" class="text-center p-4 text-muted">কোনো তথ্য পাওয়া যায়নি — No records found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($data['pages']>1): ?>
  <div class="card-footer d-flex justify-content-center">
    <nav><ul class="pagination mb-0">
      <?php for($i=1;$i<=$data['pages'];$i++): ?><li class="page-item <?= $i==$data['page']?'active':'' ?>"><a class="page-link" href="index.php?page=pcc_list&p=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a></li><?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
</form>
