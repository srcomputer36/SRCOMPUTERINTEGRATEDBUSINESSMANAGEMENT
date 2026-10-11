<?php
$pdo=Database::pdo();
$salesM=$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$purchasesM=$pdo->query("SELECT COALESCE(SUM(total),0) FROM purchases WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$expM=$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$low=$pdo->query("SELECT * FROM products WHERE stock_quantity <= low_stock_threshold AND deleted_at IS NULL LIMIT 5")->fetchAll();
?>
<div class="row g-3">
  <div class="col-md-4"><div class="stat-card"><div class="small text-muted">Sales (30d)</div><div class="h4"><?= bdt($salesM) ?></div></div></div>
  <div class="col-md-4"><div class="stat-card"><div class="small text-muted">Purchases (30d)</div><div class="h4"><?= bdt($purchasesM) ?></div></div></div>
  <div class="col-md-4"><div class="stat-card"><div class="small text-muted">Expenses (30d)</div><div class="h4"><?= bdt($expM) ?></div></div></div>
</div>
<div class="card mt-3">
  <div class="card-header">Low Stock Alert</div>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>SKU</th><th>Product</th><th>Stock</th><th>Threshold</th></tr></thead><tbody>
    <?php foreach($low as $p): ?><tr class="table-warning"><td><?= e($p['sku']) ?></td><td><?= e($p['name']) ?></td><td><?= (int)$p['stock_quantity'] ?></td><td><?= (int)$p['low_stock_threshold'] ?></td></tr><?php endforeach; if(!$low): ?><tr><td colspan="4" class="text-center text-muted">No low stock — all good</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
