<?php
$pdo=Database::pdo();
$from=$_GET['from']??date('Y-m-01');
$to=$_GET['to']??date('Y-m-d');
$status=$_GET['status']??'';
$where="WHERE received_date BETWEEN ? AND ?"; $vals=[$from,$to];
if($status){ $where.=" AND status=?"; $vals[]=$status; }
$where.=" AND deleted_at IS NULL";
$rows=$pdo->prepare("SELECT * FROM pcc_cases $where ORDER BY received_date DESC");
$rows->execute($vals); $rows=$rows->fetchAll();
$totalFee=array_sum(array_column($rows,'total_fee'));
$paid=array_sum(array_column($rows,'paid_amount'));
$due=array_sum(array_column($rows,'due_amount'));
if(isset($_GET['export']) && $_GET['export']=='csv'){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=pcc_report_'.$from.'_'.$to.'.csv');
  echo "\xEF\xBB\xBF";
  $out=fopen('php://output','w');
  fputcsv($out,['Tracking','Applicant','Passport','Mobile','District','Status','Total','Paid','Due','Received']);
  foreach($rows as $r) fputcsv($out,[$r['tracking_id'],$r['applicant_name'],$r['passport_no'],$r['mobile'],$r['district'],$r['status'],$r['total_fee'],$r['paid_amount'],$r['due_amount'],$r['received_date']]);
  fclose($out); exit;
}
?>
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="page" value="pcc_reports">
    <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-md-3"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All</option><?php foreach(['Receive','SS Printed','Sign by SP/DC','MOFA Verification','Ready for Delivery','Delivered'] as $s): ?><option <?= $status==$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-5 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-search"></i> Generate</button>
      <a href="index.php?page=pcc_reports&from=<?= e($from) ?>&to=<?= e($to) ?>&status=<?= e($status) ?>&export=csv" class="btn btn-outline-success"><i class="bi bi-download"></i> CSV</a>
      <button onclick="window.print()" type="button" class="btn btn-outline-dark"><i class="bi bi-printer"></i> Print</button>
    </div>
  </form>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">Records</div><div class="h4"><?= count($rows) ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">Total Fee</div><div class="h4"><?= bdt($totalFee) ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">Paid</div><div class="h4 text-success"><?= bdt($paid) ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">Due</div><div class="h4 text-danger"><?= bdt($due) ?></div></div></div>
</div>
<div class="card">
  <div class="card-header d-flex justify-content-between"><span>PCC Report <?= e($from) ?> → <?= e($to) ?> <?= $status?"( ".e($status)." )":"" ?></span><span class="small text-muted">Print-friendly</span></div>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>#</th><th>Tracking</th><th>Applicant</th><th>Passport</th><th>Mobile</th><th>District</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach($rows as $i=>$r): ?><tr><td><?= $i+1 ?></td><td><?= e($r['tracking_id']) ?></td><td><?= e($r['applicant_name']) ?></td><td><?= e($r['passport_no']) ?></td><td><?= e($r['mobile']) ?></td><td><?= e($r['district']) ?></td><td><span class="badge badge-status status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td><td class="text-end"><?= bdt($r['total_fee']) ?></td><td class="text-end"><?= bdt($r['paid_amount']) ?></td><td class="text-end"><?= bdt($r['due_amount']) ?></td><td><?= bd_date($r['received_date']) ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="11" class="text-center text-muted">No data</td></tr><?php endif; ?>
      </tbody>
      <tfoot class="table-light"><tr><th colspan="7" class="text-end">Total</th><th class="text-end"><?= bdt($totalFee) ?></th><th class="text-end"><?= bdt($paid) ?></th><th class="text-end"><?= bdt($due) ?></th><th></th></tr></tfoot>
    </table>
  </div>
</div>
