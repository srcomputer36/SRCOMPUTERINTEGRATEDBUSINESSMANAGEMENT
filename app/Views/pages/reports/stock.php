<?php
$pdo=Database::pdo();
$rows=$pdo->query("SELECT *, stock_quantity*purchase_price as valuation FROM products WHERE deleted_at IS NULL ORDER BY stock_quantity ASC")->fetchAll();
$totalVal=array_sum(array_column($rows,'valuation'));
if(isset($_GET['export']) && $_GET['export']=='csv'){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=stock_report.csv');
  echo "\xEF\xBB\xBF";
  $out=fopen('php://output','w');
  fputcsv($out,['SKU','Product','Category','Stock','Purchase Price','Valuation']);
  foreach($rows as $r) fputcsv($out,[$r['sku'],$r['name'],$r['category'],$r['stock_quantity'],$r['purchase_price'],$r['valuation']]);
  fclose($out); exit;
}
?>
<div class="d-flex justify-content-between mb-3"><h5>Stock Report</h5><div><span class="me-2">Total Valuation: <strong><?= bdt($totalVal) ?></strong></span><a href="index.php?page=reports_stock&export=csv" class="btn btn-sm btn-outline-success">CSV</a> <button onclick="window.print()" class="btn btn-sm btn-outline-dark">Print</button></div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Stock</th><th>Purchase</th><th>Valuation</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $r): $low=(int)$r['stock_quantity'] <= (int)$r['low_stock_threshold']; ?>
<tr class="<?= $low?'table-warning':'' ?>"><td><?= e($r['sku']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['category']) ?></td><td><?= (int)$r['stock_quantity'] ?></td><td><?= bdt($r['purchase_price']) ?></td><td><?= bdt($r['valuation']) ?></td><td><?= $low?'<span class="badge bg-danger">Low</span>':'<span class="badge bg-success">OK</span>' ?></td></tr>
<?php endforeach; ?></tbody><tfoot class="table-light"><tr><th colspan="5" class="text-end">Total Valuation</th><th><?= bdt($totalVal) ?></th><th></th></tr></tfoot></table></div></div>
