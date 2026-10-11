<?php
$pdo=Database::pdo();
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d');
$rows=$pdo->prepare("SELECT s.*, c.name as cname FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.sale_date BETWEEN ? AND ? AND s.deleted_at IS NULL ORDER BY s.sale_date DESC");
$rows->execute([$from,$to]); $rows=$rows->fetchAll();
$total=array_sum(array_column($rows,'total')); $paid=array_sum(array_column($rows,'paid')); $due=array_sum(array_column($rows,'due'));
?>
<div class="filter-bar"><form method="GET" class="row g-2"><input type="hidden" name="page" value="reports_sales"><div class="col-4"><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div><div class="col-4"><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div><div class="col-4"><button class="btn btn-primary">Filter</button> <button onclick="window.print()" type="button" class="btn btn-outline-dark">Print</button></div></form></div>
<div class="row g-2 mb-3"><div class="col-4"><div class="stat-card"><div class="small">Total</div><div class="h5"><?= bdt($total) ?></div></div></div><div class="col-4"><div class="stat-card"><div class="small">Paid</div><div class="h5 text-success"><?= bdt($paid) ?></div></div></div><div class="col-4"><div class="stat-card"><div class="small">Due</div><div class="h5 text-danger"><?= bdt($due) ?></div></div></div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Total</th><th>Paid</th><th>Due</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['invoice_no']) ?></td><td><?= e($r['cname']?:'Walk-in') ?></td><td><?= bd_date($r['sale_date']) ?></td><td><?= bdt($r['total']) ?></td><td><?= bdt($r['paid']) ?></td><td><?= bdt($r['due']) ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="6" class="text-center text-muted">No data</td></tr><?php endif; ?></tbody></table></div></div>
