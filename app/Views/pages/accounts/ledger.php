<?php
$pdo=Database::pdo();
$accountId=$_GET['account']??'';
$from=$_GET['from']??date('Y-m-01');
$to=$_GET['to']??date('Y-m-d');
$accounts=$pdo->query("SELECT * FROM accounts ORDER BY id")->fetchAll();
$where="WHERE transaction_date BETWEEN ? AND ?"; $vals=[$from,$to];
if($accountId){ $where.=" AND (account_id=? OR id IN (SELECT transaction_id FROM transaction_entries WHERE account_id=?))"; $vals[]=$accountId; $vals[]=$accountId; }
$query="SELECT t.* FROM transactions t $where ORDER BY transaction_date ASC, id ASC";
$stmt=$pdo->prepare($query); $stmt->execute($vals); $txns=$stmt->fetchAll();
?>
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="page" value="accounts">
    <div class="col-md-3"><label class="form-label small">Account</label><select name="account" class="form-select"><option value="">All</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (string)$accountId===(string)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label small">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-md-3"><button class="btn btn-primary">Filter</button> <button onclick="window.print()" type="button" class="btn btn-outline-dark">Print</button></div>
  </form>
</div>
<div class="card">
  <div class="card-header">Ledger / Cashbook • <?= e($from) ?> → <?= e($to) ?></div>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Txn No</th><th>Source</th><th>Type</th><th>Account</th><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>
    <?php $sum=0; foreach($txns as $t): $sum+=$t['type']=='expense'? -$t['amount'] : $t['amount']; ?>
    <tr><td><?= bd_date($t['transaction_date']) ?></td><td class="small"><?= e($t['transaction_no']) ?></td><td><?= e($t['source_module']) ?>#<?= (int)$t['source_id'] ?></td><td><span class="badge bg-<?= in_array($t['type'],['income','receivable'])?'success':'secondary' ?>"><?= e($t['type']) ?></span></td><td><?= e($t['account_id'] ? ($pdo->query("SELECT name FROM accounts WHERE id=".(int)$t['account_id'])->fetchColumn()?:'-') : '-') ?></td><td class="small"><?= e($t['description']) ?></td><td class="text-end"><?= bdt($t['amount']) ?> <?php if($t['type']=='expense'): ?><span class="text-danger">- </span><?php endif; ?></td></tr>
    <?php endforeach; if(!$txns): ?><tr><td colspan="7" class="text-center text-muted">No transactions</td></tr><?php endif; ?>
  </tbody></table></div>
  <div class="card-footer text-muted small">Double-entry invariant: each transaction's debits = credits. Use Reports → Profit & Loss for net.</div>
</div>
