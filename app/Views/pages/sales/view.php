<?php
$pdo=Database::pdo();
$id=(int)($_GET['id']??0);
$sale=$pdo->prepare("SELECT s.*, c.name as customer_name, c.phone FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?"); $sale->execute([$id]); $sale=$sale->fetch();
if(!$sale){ echo "<div class='alert alert-danger'>Not found</div>"; return; }
$items=$pdo->prepare("SELECT si.*, p.name, p.sku FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=?"); $items->execute([$id]); $items=$items->fetchAll();
$payments=$pdo->prepare("SELECT * FROM payments WHERE sale_id=? ORDER BY payment_date"); $payments->execute([$id]); $payments=$payments->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_payment'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF');
  else {
    $amt=(float)($_POST['amount']??0); $date=$_POST['payment_date']??date('Y-m-d'); $method=$_POST['payment_method']??'cash'; $acc=$_POST['account_id']??null;
    if($amt<=0) flash('error','Invalid amount');
    elseif($amt > (float)$sale['due']) flash('error','Exceeds due');
    else {
      $pdo->beginTransaction();
      try{
        $pdo->prepare("INSERT INTO payments (type,sale_id,customer_id,amount,payment_date,payment_method,account_id,created_by) VALUES ('sale',?,?,?,?,?,?,?)")->execute([$id,$sale['customer_id'],$amt,$date,$method,$acc,$_SESSION['user_id']??null]);
        $newPaid=(float)$sale['paid']+$amt; $newDue=(float)$sale['total']-$newPaid; $status=$newDue<=0.001?'paid':($newPaid>0?'partial':'due');
        $pdo->prepare("UPDATE sales SET paid=?, due=?, status=? WHERE id=?")->execute([$newPaid,$newDue,$status,$id]);
        $acct=new AccountingService($pdo);
        $acct->postSalePayment($id,$amt,['transaction_date'=>$date,'account_id'=>$acc,'payment_method'=>$method,'customer_id'=>$sale['customer_id']]);
        $pdo->commit(); flash('success','Payment added'); header("Location: index.php?page=sales_view&id=$id"); exit;
      }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('error',$e->getMessage()); }
    }
  }
}
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1")->fetchAll();
$sale=$pdo->prepare("SELECT s.*, c.name as customer_name, c.phone FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?"); $sale->execute([$id]); $sale=$sale->fetch();
?>
<div class="d-flex gap-2 mb-3"><a href="index.php?page=sales" class="btn btn-sm btn-outline-secondary">← Back</a><h5 class="mb-0">Invoice <?= e($sale['invoice_no']) ?></h5><span class="badge bg-<?= $sale['status']=='paid'?'success':($sale['status']=='partial'?'warning':'danger') ?> ms-auto"><?= e($sale['status']) ?></span><button onclick="window.print()" class="btn btn-sm btn-outline-dark">Print</button></div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between"><span>Invoice Details</span><span class="small text-muted"><?= bd_date($sale['sale_date']) ?> • <?= e($sale['customer_name']?:'Walk-in') ?> <?= $sale['phone']?'('.e($sale['phone']).')':'' ?></span></div>
      <div class="card-body">
        <div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Product</th><th>SKU</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>
          <?php foreach($items as $i=>$it): ?><tr><td><?= $i+1 ?></td><td><?= e($it['name']) ?></td><td><?= e($it['sku']) ?></td><td><?= (int)$it['quantity'] ?></td><td><?= bdt($it['unit_price']) ?></td><td><?= bdt($it['total']) ?></td></tr><?php endforeach; ?>
        </tbody><tfoot class="table-light"><tr><th colspan="5" class="text-end">Subtotal</th><th><?= bdt($sale['subtotal']) ?></th></tr><tr><th colspan="5" class="text-end">Discount</th><th><?= bdt($sale['discount']) ?></th></tr><tr><th colspan="5" class="text-end">Total</th><th><?= bdt($sale['total']) ?></th></tr><tr><th colspan="5" class="text-end">Paid</th><th class="text-success"><?= bdt($sale['paid']) ?></th></tr><tr><th colspan="5" class="text-end">Due</th><th class="text-danger"><?= bdt($sale['due']) ?></th></tr></tfoot></table></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Payment</div>
      <div class="card-body">
        <div class="mb-2 d-flex justify-content-between"><span>Total</span><strong><?= bdt($sale['total']) ?></strong></div>
        <div class="mb-2 d-flex justify-content-between"><span>Due</span><strong class="text-danger"><?= bdt($sale['due']) ?></strong></div>
        <?php if((float)$sale['due']>0): ?>
        <form method="POST">
          <?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" max="<?= (float)$sale['due'] ?>" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="mb-2"><label class="form-label">Account</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
          <div class="mb-2"><label class="form-label">Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="bank">Bank</option></select></div>
          <button name="add_payment" value="1" class="btn btn-success w-100">Add Payment</button>
        </form>
        <?php else: ?><div class="alert alert-success">Fully Paid</div><?php endif; ?>
        <hr><h6 class="small">History</h6>
        <?php foreach($payments as $p): ?><div class="small d-flex justify-content-between"><span><?= bd_date($p['payment_date']) ?> <?= e($p['payment_method']) ?></span><span><?= bdt($p['amount']) ?></span></div><?php endforeach; if(!$payments): ?><div class="text-muted small">No payments</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>
