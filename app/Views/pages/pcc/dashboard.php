<?php
$pdo=Database::pdo();
$svc=new PccService($pdo);
$stats=$svc->stats();
$byDistrict=$pdo->query("SELECT district, COUNT(*) c FROM pcc_cases WHERE deleted_at IS NULL GROUP BY district ORDER BY c DESC LIMIT 10")->fetchAll();
$recentPay=$pdo->query("SELECT p.*, c.tracking_id, c.applicant_name FROM payments p JOIN pcc_cases c ON c.id=p.case_id WHERE p.type='pcc' ORDER BY p.created_at DESC LIMIT 5")->fetchAll();
?>
<div class="row g-3">
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">মোট কেস</div><div class="h2"><?= (int)$stats['total'] ?></div><div class="small text-muted"><?= bdt($stats['paidSum']) ?> collected</div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">আজকের রিসিভ</div><div class="h2"><?= (int)$stats['today'] ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">মোট Due</div><div class="h2 text-danger"><?= bdt($stats['dueSum']) ?></div></div></div>
  <div class="col-md-3"><div class="stat-card"><div class="text-muted small">Delivered</div><div class="h2"><?= (int)($stats['byStatus']['Delivered']??0) ?></div></div></div>
</div>
<div class="row g-3 mt-1">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Monthly Trend</div>
      <div class="card-body"><canvas id="monthlyChart" height="100"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Top Districts</div>
      <div class="list-group list-group-flush">
        <?php foreach($byDistrict as $d): ?><div class="list-group-item d-flex justify-content-between"><span><?= e($d['district']?:'—') ?></span><span class="badge bg-primary"><?= (int)$d['c'] ?></span></div><?php endforeach; if(!$byDistrict): ?><div class="p-3 text-muted">No data</div><?php endif; ?>
      </div>
    </div>
    <div class="card mt-3">
      <div class="card-header">Recent Payments</div>
      <div class="list-group list-group-flush">
        <?php foreach($recentPay as $p): ?><div class="list-group-item"><div class="d-flex justify-content-between"><strong><?= e($p['tracking_id']) ?></strong><span><?= bdt($p['amount']) ?></span></div><small class="text-muted"><?= e($p['applicant_name']) ?> • <?= bd_date($p['payment_date']) ?></small></div><?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<script>
const mctx=document.getElementById('monthlyChart');
if(mctx){
  const months=<?= json_encode(array_reverse(array_column($stats['monthly'],'m'))) ?>;
  const counts=<?= json_encode(array_reverse(array_column($stats['monthly'],'c'))) ?>;
  new Chart(mctx,{type:'line',data:{labels:months,datasets:[{label:'Cases',data:counts,borderColor:'#0d6efd',tension:.3,fill:false}]},options:{plugins:{legend:{display:false}}}});
}
</script>
