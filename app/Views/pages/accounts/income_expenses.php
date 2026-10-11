<?php
$pdo=Database::pdo();
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1")->fetchAll();
$cats=$pdo->query("SELECT * FROM income_expense_categories ORDER BY type, name")->fetchAll();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_entry'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    $type=$_POST['entry_type']??'expense'; $cat=trim($_POST['category']??''); $amount=(float)($_POST['amount']??0);
    $date=$_POST['entry_date']??date('Y-m-d'); $account=(int)($_POST['account_id']??0); $desc=trim($_POST['description']??'');
    if($amount<=0) $err="Invalid amount";
    else {
      $pdo->beginTransaction();
      try{
        if($type==='income'){
          $pdo->prepare("INSERT INTO incomes (category,description,amount,income_date,payment_method,account_id,created_by) VALUES (?,?,?,?,?,?,?)")->execute([$cat,$desc,$amount,$date,$_POST['payment_method']??'cash',$account,$_SESSION['user_id']??null]);
          $id=$pdo->lastInsertId();
          $acct=new AccountingService($pdo);
          $acct->postIncome($id,$amount,['transaction_date'=>$date,'account_id'=>$account,'description'=>$desc]);
          $pdo->prepare("UPDATE accounts SET balance=balance+? WHERE id=?")->execute([$amount,$account]);
        } else {
          $pdo->prepare("INSERT INTO expenses (category,description,amount,expense_date,payment_method,account_id,created_by) VALUES (?,?,?,?,?,?,?)")->execute([$cat,$desc,$amount,$date,$_POST['payment_method']??'cash',$account,$_SESSION['user_id']??null]);
          $id=$pdo->lastInsertId();
          $acct=new AccountingService($pdo);
          $acct->postExpense($id,$amount,['transaction_date'=>$date,'account_id'=>$account,'description'=>$desc]);
          $pdo->prepare("UPDATE accounts SET balance=balance-? WHERE id=?")->execute([$amount,$account]);
        }
        $pdo->commit();
        log_activity($pdo,'create',$type,$id,"$type $amount");
        flash('success', ucfirst($type)." recorded"); header("Location: index.php?page=income_expenses"); exit;
      }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); $err=$e->getMessage(); }
    }
  }
}
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d');
$incomes=$pdo->prepare("SELECT * FROM incomes WHERE income_date BETWEEN ? AND ? ORDER BY income_date DESC"); $incomes->execute([$from,$to]); $incomes=$incomes->fetchAll();
$expenses=$pdo->prepare("SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC"); $expenses->execute([$from,$to]); $expenses=$expenses->fetchAll();
$incSum=array_sum(array_column($incomes,'amount')); $expSum=array_sum(array_column($expenses,'amount'));
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Add Income / Expense</div>
      <div class="card-body">
        <form method="POST">
          <?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Type</label><select name="entry_type" class="form-select"><option value="income">Income</option><option value="expense">Expense</option></select></div>
          <div class="mb-2"><label class="form-label">Category</label><input list="catList" name="category" class="form-control" required><datalist id="catList"><?php foreach($cats as $c): ?><option value="<?= e($c['name']) ?>"><?= e($c['type']) ?></option><?php endforeach; ?></datalist></div>
          <div class="mb-2"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Date</label><input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="mb-2"><label class="form-label">Account</label><select name="account_id" class="form-select" required><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= bdt($a['balance']) ?>)</option><?php endforeach; ?></select></div>
          <div class="mb-2"><label class="form-label">Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option><option value="bkash">bKash</option></select></div>
          <div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control"></div>
          <button name="add_entry" value="1" class="btn btn-primary w-100">Save</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="filter-bar">
      <form method="GET" class="row g-2"><input type="hidden" name="page" value="income_expenses"><div class="col-5"><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div><div class="col-5"><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div><div class="col-2"><button class="btn btn-primary w-100">Filter</button></div></form>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-4"><div class="stat-card"><div class="small text-muted">Income</div><div class="h5 text-success"><?= bdt($incSum) ?></div></div></div>
      <div class="col-4"><div class="stat-card"><div class="small text-muted">Expense</div><div class="h5 text-danger"><?= bdt($expSum) ?></div></div></div>
      <div class="col-4"><div class="stat-card"><div class="small text-muted">Net</div><div class="h5"><?= bdt($incSum-$expSum) ?></div></div></div>
    </div>
    <div class="card mb-3"><div class="card-header">Expenses</div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead><tbody><?php foreach($expenses as $e): ?><tr><td><?= bd_date($e['expense_date']) ?></td><td><?= e($e['category']) ?></td><td><?= e($e['description']) ?></td><td><?= bdt($e['amount']) ?></td></tr><?php endforeach; if(!$expenses): ?><tr><td colspan="4" class="text-center text-muted">No expenses</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="card"><div class="card-header">Incomes</div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead><tbody><?php foreach($incomes as $e): ?><tr><td><?= bd_date($e['income_date']) ?></td><td><?= e($e['category']) ?></td><td><?= e($e['description']) ?></td><td><?= bdt($e['amount']) ?></td></tr><?php endforeach; if(!$incomes): ?><tr><td colspan="4" class="text-center text-muted">No incomes</td></tr><?php endif; ?></tbody></table></div></div>
  </div>
</div>
