<?php
$pdo=Database::pdo();
$id=(int)($_GET['id']??0);
$c=$pdo->prepare("SELECT * FROM customers WHERE id=?"); $c->execute([$id]); $c=$c->fetch();
if(!$c){ echo "<div class='alert alert-danger'>Not found</div>"; return; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_customer'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF');
  else {
    $pdo->prepare("UPDATE customers SET name=?, phone=?, email=?, address=? WHERE id=?")->execute([trim($_POST['name']),trim($_POST['phone']),trim($_POST['email']),trim($_POST['address']),$id]);
    flash('success','Updated'); header("Location: index.php?page=customers_view&id=$id"); exit;
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_payment'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF');
  else {
    $amt=(float)($_POST['amount']??0);
    $date=$_POST['payment_date']??date('Y-m-d');
    $method=$_POST['payment_method']??'cash';
    $account=$_POST['account_id']??null;
    $pdo->prepare("INSERT INTO payments (type,customer_id,amount,payment_date,payment_method,account_id,created_by) VALUES ('customer',?,?,?,?,?,?)")->execute([$id,$amt,$date,$method,$account,$_SESSION['user_id']??null]);
    // accounting: debit cash, credit receivable
    $acct=new AccountingService($pdo);
    $acct->post('customer_payment',$pdo->lastInsertId(),'income',$amt,[
      ['account_id'=>$account,'debit'=>$amt,'credit'=>0,'description'=>'Customer payment'],
      ['account_id'=>null,'debit'=>0,'credit'=>$amt,'description'=>'Receivable clear'],
    ],['transaction_date'=>$date,'customer_id'=>$id,'payment_method'=>$method]);
    // also need to reduce sales due? For simplicity, payments table tracks; ledger sums.
    flash('success','Payment recorded'); header("Location: index.php?page=customers_view&id=$id"); exit;
  }
}
$sales=$pdo->prepare("SELECT * FROM sales WHERE customer_id=? ORDER BY sale_date DESC"); $sales->execute([$id]); $sales=$sales->fetchAll();
$pccs=$pdo->prepare("SELECT * FROM pcc_cases WHERE customer_id=? ORDER BY received_date DESC"); $pccs->execute([$id]); $pccs=$pccs->fetchAll();
$payments=$pdo->prepare("SELECT * FROM payments WHERE customer_id=? ORDER BY payment_date DESC"); $payments->execute([$id]); $payments=$payments->fetchAll();
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1")->fetchAll();
$saleDue=array_sum(array_column($sales,'due'));
$pccDue=array_sum(array_column($pccs,'due_amount'));
$totalDue=$saleDue+$pccDue;
?>
<div class="d-flex gap-2 mb-3"><a href="index.php?page=customers" class="btn btn-sm btn-outline-secondary">← Back</a><h5 class="mb-0"><?= e($c['name']) ?> <small class="text-muted"><?= e($c['phone']) ?></small></h5></div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header">Profile</div>
      <div class="card-body">
        <form method="POST">
          <?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($c['name']) ?>"></div>
          <div class="mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($c['phone']) ?>"></div>
          <div class="mb-2"><label class="form-label">Email</label><input name="email" class="form-control" value="<?= e($c['email']) ?>"></div>
          <div class="mb-2"><label class="form-label">Address</label><textarea name="address" class="form-control"><?= e($c['address']) ?></textarea></div>
          <button name="update_customer" value="1" class="btn btn-primary w-100">Update</button>
        </form>
        <hr>
        <div class="d-flex justify-content-between"><span>Total Due</span><strong class="text-danger"><?= bdt($totalDue) ?></strong></div>
        <div class="small text-muted">Sales due <?= bdt($saleDue) ?> + PCC due <?= bdt($pccDue) ?></div>
      </div>
    </div>
    <div class="card mt-3">
      <div class="card-header">Receive Payment</div>
      <div class="card-body">
        <form method="POST">
          <?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="mb-2"><label class="form-label">Account</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
          <div class="mb-2"><label class="form-label">Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="bank">Bank</option></select></div>
          <button name="add_payment" value="1" class="btn btn-success w-100">Add Payment</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-header">Sales (<?= count($sales) ?>)</div>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Due</th></tr></thead><tbody><?php foreach($sales as $s): ?><tr><td><a href="index.php?page=sales_view&id=<?= (int)$s['id'] ?>"><?= e($s['invoice_no']) ?></a></td><td><?= bd_date($s['sale_date']) ?></td><td><?= bdt($s['total']) ?></td><td><?= bdt($s['due']) ?></td></tr><?php endforeach; if(!$sales): ?><tr><td colspan="4" class="text-center text-muted">No sales</td></tr><?php endif; ?></tbody></table></div>
    </div>
    <div class="card mb-3">
      <div class="card-header">PCC Cases (<?= count($pccs) ?>)</div>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Tracking</th><th>Status</th><th>Total</th><th>Due</th></tr></thead><tbody><?php foreach($pccs as $p): ?><tr><td><a href="index.php?page=pcc_view&id=<?= (int)$p['id'] ?>"><?= e($p['tracking_id']) ?></a></td><td><?= e($p['status']) ?></td><td><?= bdt($p['total_fee']) ?></td><td><?= bdt($p['due_amount']) ?></td></tr><?php endforeach; if(!$pccs): ?><tr><td colspan="4" class="text-center text-muted">No PCC</td></tr><?php endif; ?></tbody></table></div>
    </div>
    <div class="card">
      <div class="card-header">Payment History (Unified Ledger)</div>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Amount</th><th>Method</th></tr></thead><tbody><?php foreach($payments as $pay): ?><tr><td><?= bd_date($pay['payment_date']) ?></td><td><?= bdt($pay['amount']) ?></td><td><?= e($pay['payment_method']) ?></td></tr><?php endforeach; if(!$payments): ?><tr><td colspan="3" class="text-center text-muted">No payments</td></tr><?php endif; ?></tbody></table></div>
    </div>
  </div>
</div>
