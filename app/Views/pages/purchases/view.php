<?php
$pdo=Database::pdo();
$id=(int)($_GET['id']??0);
$pur=$pdo->prepare("SELECT pu.*, s.name as supplier_name FROM purchases pu LEFT JOIN suppliers s ON s.id=pu.supplier_id WHERE pu.id=?"); $pur->execute([$id]); $pur=$pur->fetch();
if(!$pur){ echo "<div class='alert alert-danger'>Not found</div>"; return; }
$items=$pdo->prepare("SELECT pi.*, p.name, p.sku FROM purchase_items pi JOIN products p ON p.id=pi.product_id WHERE pi.purchase_id=?"); $items->execute([$id]); $items=$items->fetchAll();
?>
<div class="d-flex gap-2 mb-3"><a href="index.php?page=purchases" class="btn btn-sm btn-outline-secondary">← Back</a><h5 class="mb-0">Purchase <?= e($pur['invoice_no']) ?></h5><span class="badge bg-<?= $pur['status']=='paid'?'success':'warning' ?> ms-auto"><?= e($pur['status']) ?></span><button onclick="window.print()" class="btn btn-sm btn-outline-dark">Print</button></div>
<div class="card">
  <div class="card-header d-flex justify-content-between"><span><?= e($pur['supplier_name']?:'No supplier') ?> • <?= bd_date($pur['purchase_date']) ?></span><span><?= bdt($pur['total']) ?></span></div>
  <div class="card-body">
    <table class="table"><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Cost</th><th>Total</th></tr></thead><tbody><?php foreach($items as $i=>$it): ?><tr><td><?= $i+1 ?></td><td><?= e($it['name']) ?> (<?= e($it['sku']) ?>)</td><td><?= (int)$it['quantity'] ?></td><td><?= bdt($it['unit_cost']) ?></td><td><?= bdt($it['total']) ?></td></tr><?php endforeach; ?></tbody><tfoot class="table-light"><tr><th colspan="4" class="text-end">Subtotal</th><th><?= bdt($pur['subtotal']) ?></th></tr><tr><th colspan="4" class="text-end">Discount</th><th><?= bdt($pur['discount']) ?></th></tr><tr><th colspan="4" class="text-end">Total</th><th><?= bdt($pur['total']) ?></th></tr><tr><th colspan="4" class="text-end">Paid</th><th><?= bdt($pur['paid']) ?></th></tr><tr><th colspan="4" class="text-end">Due</th><th class="text-danger"><?= bdt($pur['due']) ?></th></tr></tfoot></table>
  </div>
</div>
