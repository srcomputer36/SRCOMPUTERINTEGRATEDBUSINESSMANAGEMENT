<?php
$pdo=Database::pdo();
$search=trim($_GET['search']??'');
$where="WHERE s.deleted_at IS NULL"; $vals=[];
if($search){ $where.=" AND (s.invoice_no LIKE ? OR c.name LIKE ?)"; $vals=["%$search%","%$search%"]; }
$rows=$pdo->prepare("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON c.id=s.customer_id $where ORDER BY s.created_at DESC LIMIT 100");
$rows->execute($vals); $rows=$rows->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="filter-bar mb-0 flex-grow-1 me-2"><form method="GET" class="d-flex gap-2"><input type="hidden" name="page" value="sales"><input name="search" class="form-control" placeholder="Search invoice/customer" value="<?= e($search) ?>"><button class="btn btn-primary">Search</button></form></div>
  <a href="index.php?page=sales_create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Sale</a>
</div>
<div class="card">
  <div class="card-header">Sales (<?= count($rows) ?>)</div>
  <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php foreach($rows as $r): ?>
    <tr>
      <td><a href="index.php?page=sales_view&id=<?= (int)$r['id'] ?>"><?= e($r['invoice_no']) ?></a></td>
      <td><?= e($r['customer_name']?:'Walk-in') ?></td><td><?= bd_date($r['sale_date']) ?></td>
      <td><?= bdt($r['total']) ?></td><td class="text-success"><?= bdt($r['paid']) ?></td><td class="text-danger"><?= bdt($r['due']) ?></td>
      <td><span class="badge bg-<?= $r['status']=='paid'?'success':($r['status']=='partial'?'warning':'danger') ?>"><?= e($r['status']) ?></span></td>
      <td><a href="index.php?page=sales_view&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
    </tr>
    <?php endforeach; if(!$rows): ?><tr><td colspan="8" class="text-center text-muted p-3">No sales yet</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
