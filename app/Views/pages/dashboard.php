<?php
$pdo = Database::pdo();
$pcc = (new PccService($pdo))->stats();

// Business stats
$customers = $pdo->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL")->fetchColumn();
$products = $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND deleted_at IS NULL")->fetchColumn();
$salesTotal = $pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE deleted_at IS NULL")->fetchColumn();
$purchasesTotal = $pdo->query("SELECT COALESCE(SUM(total),0) FROM purchases WHERE deleted_at IS NULL")->fetchColumn();
$expensesTotal = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
$incomesTotal = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM incomes")->fetchColumn();
$receivables = $pdo->query("SELECT COALESCE(SUM(due),0) FROM sales WHERE deleted_at IS NULL")->fetchColumn();
$receivables += (float)$pcc['dueSum'];
$payables = $pdo->query("SELECT COALESCE(SUM(due),0) FROM purchases WHERE deleted_at IS NULL")->fetchColumn();
$cashBal = $pdo->query("SELECT COALESCE(SUM(balance),0) FROM accounts")->fetchColumn();
// Recent cases
$recent = $pdo->query("SELECT * FROM pcc_cases WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="d-flex justify-content-between align-items-start">
        <div><div class="text-muted small">মোট PCC কেস / Total Cases</div><div class="h3 fw-bold mt-1"><?= (int)$pcc['total'] ?></div><div class="small text-success">আজ <?= (int)$pcc['today'] ?> টি</div></div>
        <div class="icon bg-primary text-white"><i class="bi bi-folder2"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="d-flex justify-content-between">
        <div><div class="text-muted small">মোট বাকি / Total Due</div><div class="h3 fw-bold mt-1"><?= bdt($pcc['dueSum']) ?></div><div class="small text-muted">আদায় <?= bdt($pcc['paidSum']) ?></div></div>
        <div class="icon bg-warning"><i class="bi bi-cash"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="d-flex justify-content-between">
        <div><div class="text-muted small">Sales / বিক্রয়</div><div class="h3 fw-bold mt-1"><?= bdt($salesTotal) ?></div><div class="small text-muted">Products <?= (int)$products ?> • Low <?= (int)$lowStock ?></div></div>
        <div class="icon bg-success text-white"><i class="bi bi-receipt"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="d-flex justify-content-between">
        <div><div class="text-muted small">Net Position</div><div class="h6 fw-bold mt-1">Receivable <?= bdt($receivables) ?></div><div class="small">Payable <?= bdt($payables) ?></div></div>
        <div class="icon bg-info text-white"><i class="bi bi-bank"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">PCC Status Overview <a href="index.php?page=pcc_list" class="btn btn-sm btn-outline-primary">View All</a></div>
      <div class="card-body">
        <?php
        $labels = ['Receive','SS Printed','Sign by SP/DC','MOFA Verification','Ready for Delivery','Delivered'];
        $colors = ['#f59e0b','#3b82f6','#6366f1','#ec4899','#22c55e','#94a3b8'];
        ?>
        <div class="row g-2 mb-3">
          <?php foreach($labels as $i=>$lab): $cnt=$pcc['byStatus'][$lab]??0; ?>
            <div class="col-6 col-md-4"><div class="border rounded p-2 text-center"><div class="small text-muted"><?= e($lab) ?></div><div class="h5 mb-0"><?= (int)$cnt ?></div></div></div>
          <?php endforeach; ?>
        </div>
        <canvas id="statusChart" height="120"></canvas>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Recent PCC Cases</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Tracking</th><th>Applicant</th><th>Mobile</th><th>Status</th><th>Due</th></tr></thead>
          <tbody>
          <?php foreach($recent as $r): ?>
            <tr>
              <td><a href="index.php?page=pcc_view&id=<?= (int)$r['id'] ?>"><?= e($r['tracking_id']) ?></a></td>
              <td><?= e($r['applicant_name']) ?><?php if($r['applicant_name_bn']): ?><br><small class="text-muted"><?= e($r['applicant_name_bn']) ?></small><?php endif; ?></td>
              <td><?= e($r['mobile']) ?></td>
              <td><span class="badge badge-status status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
              <td><?= bdt($r['due_amount']) ?></td>
            </tr>
          <?php endforeach; if(!$recent): ?><tr><td colspan="5" class="text-center text-muted p-4">কোনো তথ্য নেই</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">Business Snapshot</div>
      <div class="card-body">
        <div class="d-flex justify-content-between py-2 border-bottom"><span>Customers</span><strong><?= (int)$customers ?></strong></div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span>Sales Total</span><strong><?= bdt($salesTotal) ?></strong></div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span>Purchases</span><strong><?= bdt($purchasesTotal) ?></strong></div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span>Expenses</span><strong><?= bdt($expensesTotal) ?></strong></div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span>Incomes</span><strong><?= bdt($incomesTotal) ?></strong></div>
        <div class="d-flex justify-content-between py-2"><span>Cash/Bank Balance</span><strong><?= bdt($cashBal) ?></strong></div>
        <?php if($lowStock>0): ?><div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> <?= (int)$lowStock ?> product(s) low stock!</div><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header">Quick Actions</div>
      <div class="card-body d-grid gap-2">
        <a href="index.php?page=pcc_receive" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New PCC Case</a>
        <a href="index.php?page=sales_create" class="btn btn-outline-primary"><i class="bi bi-receipt"></i> New Sale</a>
        <a href="index.php?page=purchases_create" class="btn btn-outline-success"><i class="bi bi-bag-plus"></i> New Purchase</a>
        <a href="index.php?page=customers" class="btn btn-outline-secondary"><i class="bi bi-people"></i> Customers</a>
      </div>
    </div>
  </div>
</div>
<script>
const ctx=document.getElementById('statusChart');
if(ctx){
  new Chart(ctx,{
    type:'bar',
    data:{
      labels: <?= json_encode($labels) ?>,
      datasets:[{label:'Cases', data: <?= json_encode(array_map(fn($l)=> $pcc['byStatus'][$l]??0, $labels)) ?>, backgroundColor: <?= json_encode($colors) ?> }]
    },
    options:{responsive:true, plugins:{legend:{display:false}}}
  });
}
</script>
