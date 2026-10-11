<?php
$pdo=Database::pdo();
$rows=$pdo->query("SELECT pu.*, s.name as supplier_name FROM purchases pu LEFT JOIN suppliers s ON s.id=pu.supplier_id WHERE pu.deleted_at IS NULL ORDER BY pu.created_at DESC LIMIT 100")->fetchAll();
?>
<div class="d-flex justify-content-between mb-3"><h5>Purchases</h5><a href="index.php?page=purchases_create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Purchase</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th>View</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr><td><a href="index.php?page=purchases_view&id=<?= (int)$r['id'] ?>"><?= e($r['invoice_no']) ?></a></td><td><?= e($r['supplier_name']?:'—') ?></td><td><?= bd_date($r['purchase_date']) ?></td><td><?= bdt($r['total']) ?></td><td><?= bdt($r['paid']) ?></td><td class="text-danger"><?= bdt($r['due']) ?></td><td><span class="badge bg-<?= $r['status']=='paid'?'success':'warning' ?>"><?= e($r['status']) ?></span></td><td><a href="index.php?page=purchases_view&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="8" class="text-center text-muted">No purchases</td></tr><?php endif; ?>
</tbody></table></div></div>
