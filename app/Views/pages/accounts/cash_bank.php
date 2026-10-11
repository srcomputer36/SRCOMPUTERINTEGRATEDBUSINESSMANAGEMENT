<?php
$pdo=Database::pdo();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_account'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    $name=trim($_POST['name']??''); $type=$_POST['type']??'cash'; $bal=(float)($_POST['opening_balance']??0);
    if(!$name) $err="Name required";
    else { $pdo->prepare("INSERT INTO accounts (name,type,opening_balance,balance) VALUES (?,?,?,?)")->execute([$name,$type,$bal,$bal]); flash('success','Account added'); header("Location: index.php?page=cash_bank"); exit; }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['transfer'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    $from=(int)$_POST['from_account']; $to=(int)$_POST['to_account']; $amt=(float)$_POST['amount'];
    if($from==$to) $err="Same account";
    elseif($amt<=0) $err="Invalid amount";
    else {
      $fromRow=$pdo->prepare("SELECT * FROM accounts WHERE id=?"); $fromRow->execute([$from]); $fromRow=$fromRow->fetch();
      $toRow=$pdo->prepare("SELECT * FROM accounts WHERE id=?"); $toRow->execute([$to]); $toRow=$toRow->fetch();
      if(!$fromRow||!$toRow) $err="Account not found";
      elseif((float)$fromRow['balance'] < $amt) $err="Insufficient balance in ".$fromRow['name'];
      else {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE accounts SET balance=balance-? WHERE id=?")->execute([$amt,$from]);
        $pdo->prepare("UPDATE accounts SET balance=balance+? WHERE id=?")->execute([$amt,$to]);
        $acct=new AccountingService($pdo);
        $acct->postTransfer($from,$to,$amt,['transaction_date'=>date('Y-m-d'),'description'=>"Transfer {$fromRow['name']} → {$toRow['name']}",'transfer_id'=>time()]);
        $pdo->commit();
        log_activity($pdo,'transfer','accounts',0,"Transfer $amt $from → $to");
        flash('success','Transfer completed'); header("Location: index.php?page=cash_bank"); exit;
      }
    }
  }
}
$accounts=$pdo->query("SELECT * FROM accounts ORDER BY id")->fetchAll();
// recalc balances from transactions? Keep stored but show also computed from opening + inflows
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Cash & Bank Accounts</div>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Account</th><th>Type</th><th>Opening</th><th>Balance</th></tr></thead><tbody>
        <?php foreach($accounts as $a): ?><tr><td><?= e($a['name']) ?></td><td><span class="badge bg-secondary"><?= e($a['type']) ?></span></td><td><?= bdt($a['opening_balance']) ?></td><td><strong><?= bdt($a['balance']) ?></strong></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
    <div class="card mt-3">
      <div class="card-header">Account Transfer (Not income/expense)</div>
      <div class="card-body">
        <form method="POST" class="row g-2">
          <?= Security::csrfField() ?>
          <div class="col-md-3"><label class="form-label">From</label><select name="from_account" class="form-select" required><option value="">Select</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= bdt($a['balance']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">To</label><select name="to_account" class="form-select" required><option value="">Select</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" class="form-control" required></div>
          <div class="col-md-3 d-flex align-items-end"><button name="transfer" value="1" class="btn btn-warning w-100">Transfer</button></div>
        </form>
        <div class="small text-muted mt-2">Transfer does not affect Profit/Loss. Balanced double entry created.</div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Add Account</div>
      <div class="card-body">
        <form method="POST"><?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Name *</label><input name="name" class="form-control" required placeholder="e.g. Cash, DBBL Bank"></div>
          <div class="mb-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile</option></select></div>
          <div class="mb-2"><label class="form-label">Opening Balance</label><input name="opening_balance" type="number" step="0.01" class="form-control" value="0"></div>
          <button name="add_account" value="1" class="btn btn-primary w-100">Add Account</button>
        </form>
      </div>
    </div>
  </div>
</div>
