<?php
$pdo=Database::pdo();
$id=(int)($_GET['id']??0);
$s=$pdo->prepare("SELECT * FROM suppliers WHERE id=?"); $s->execute([$id]); $s=$s->fetch();
if(!$s){ echo "<div class='alert alert-danger'>Not found</div>"; return; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_payment'])){
  $amt=(float)($_POST['amount']??0); $date=$_POST['payment_date']??date('Y-m-d'); $acc=$_POST['account_id']??null;
  $pdo->prepare("INSERT INTO payments (type,supplier_id,amount,payment_date,payment_method,account_id,created_by) VALUES ('supplier',?,?,?,?,?,?)")->execute([$id,$amt,$date,$_POST['payment_method']??'cash',$acc,$_SESSION['user_id']??null]);
  $acct=new AccountingService($pdo);
  $acct->post('supplier_payment',$pdo->lastInsertId(),'payable',$amt,[
    ['account_id'=>null,'debit'=>$amt,'credit'=>0,'description'=>'Payable clear'],
    ['account_id'=>$acc,'debit'=>0,'credit'=>$amt,'description'=>'Cash out'],
  ],['transaction_date'=>$date,'supplier_id'=>$id]);
  flash('success','Payment recorded'); header("Location: index.php?page=suppliers_view&id=$id"); exit;
}
$purchases=$pdo->prepare("SELECT * FROM purchases WHERE supplier_id=? ORDER BY purchase_date DESC"); $purchases->execute([$id]); $purchases=$purchases->fetchAll();
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1")->fetchAll();
$due=array_sum(array_column($purchases,'due'));
?>
<div class="d-flex gap-2 mb-3"><a href="index.php?page=suppliers" class="btn btn-sm btn-outline-secondary">← Back</a><h5><?= e($s['name']) ?> <small class="text-muted"><?= e($s['phone']) ?></small></h5></div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card"><div class="card-header">Info</div><div class="card-body">
      <div>Company: <?= e($s['company_name']?:'—') ?></div><div>Phone: <?= e($s['phone']) ?></div><div>Email: <?= e($s['email']?:'—') ?></div><div>Address: <?= e($s['address']?:'—') ?></div>
      <hr><div class="d-flex justify-content-between"><span>Payable</span><strong class="text-danger"><?= bdt($due) ?></strong></div>
    </div></div>
    <div class="card mt-3"><div class="card-header">Pay Supplier</div><div class="card-body">
      <form method="POST"><?= Security::csrfField() ?>
        <div class="mb-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-2"><label class="form-label">Account</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option></select></div>
        <button name="add_payment" value="1" class="btn btn-success w-100">Pay</button>
      </form>
    </div></div>
  </div>
  <div class="col-md-8">
    <div class="card"><div class="card-header">Purchases</div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Due</th></tr></thead><tbody><?php foreach($purchases as $p): ?><tr><td><a href="index.php?page=purchases_view&id=<?= (int)$p['id'] ?>"><?= e($p['invoice_no']) ?></a></td><td><?= bd_date($p['purchase_date']) ?></td><td><?= bdt($p['total']) ?></td><td><?= bdt($p['due']) ?></td></tr><?php endforeach; if(!$purchases): ?><tr><td colspan="4" class="text-center text-muted">No purchases</td></tr><?php endif; ?></tbody></table></div></div>
  </div>
</div>
