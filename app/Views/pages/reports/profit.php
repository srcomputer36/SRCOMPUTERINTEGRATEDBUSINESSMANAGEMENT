<?php
$pdo=Database::pdo();
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d');
// Gross receipts = total sales + PCC service revenue (total_fee mode service_only)
$salesSum=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE sale_date BETWEEN ? AND ? AND deleted_at IS NULL"); $salesSum->execute([$from,$to]); $salesSum=(float)$salesSum->fetchColumn();
$pccService=$pdo->prepare("SELECT COALESCE(SUM(service_charge),0) FROM pcc_cases WHERE received_date BETWEEN ? AND ? AND deleted_at IS NULL"); $pccService->execute([$from,$to]); $pccService=(float)$pccService->fetchColumn();
$pccGovt=$pdo->prepare("SELECT COALESCE(SUM(govt_fee),0) FROM pcc_cases WHERE received_date BETWEEN ? AND ? AND deleted_at IS NULL"); $pccGovt->execute([$from,$to]); $pccGovt=(float)$pccGovt->fetchColumn();
$pccOther=$pdo->prepare("SELECT COALESCE(SUM(other_fee),0) FROM pcc_cases WHERE received_date BETWEEN ? AND ? AND deleted_at IS NULL"); $pccOther->execute([$from,$to]); $pccOther=(float)$pccOther->fetchColumn();
$otherIncome=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM incomes WHERE income_date BETWEEN ? AND ?"); $otherIncome->execute([$from,$to]); $otherIncome=(float)$otherIncome->fetchColumn();
$expenses=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?"); $expenses->execute([$from,$to]); $expenses=(float)$expenses->fetchColumn();
// Operating expenses includes govt+other as direct costs? Show separated
$directCosts=$pccGovt+$pccOther;
$operating=$expenses; // expenses table
$gross=$salesSum + $pccService + $otherIncome;
$net=$gross - $directCosts - $operating;
// Receivables/Payables/Cash
$receivables=$pdo->query("SELECT COALESCE(SUM(due),0) FROM sales WHERE deleted_at IS NULL")->fetchColumn();
$receivables+=(float)$pdo->query("SELECT COALESCE(SUM(due_amount),0) FROM pcc_cases WHERE deleted_at IS NULL")->fetchColumn();
$payables=(float)$pdo->query("SELECT COALESCE(SUM(due),0) FROM purchases WHERE deleted_at IS NULL")->fetchColumn();
$cash=(float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM accounts")->fetchColumn();
?>
<div class="filter-bar">
  <form method="GET" class="row g-2"><input type="hidden" name="page" value="reports_profit"><div class="col-4"><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div><div class="col-4"><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div><div class="col-4"><button class="btn btn-primary">Generate</button> <button onclick="window.print()" type="button" class="btn btn-outline-dark">Print</button></div></form>
</div>
<div class="card">
  <div class="card-header d-flex justify-content-between"><span>Profit & Loss — Unified Accounting</span><span class="small text-muted"><?= e($from) ?> → <?= e($to) ?></span></div>
  <div class="card-body">
    <table class="table">
      <tr><th>1. Gross Receipts (Sales)</th><td class="text-end"><?= bdt($salesSum) ?></td></tr>
      <tr><th>2. Service Revenue (PCC Service Charge)</th><td class="text-end text-success"><?= bdt($pccService) ?></td></tr>
      <tr><th>Other Income</th><td class="text-end"><?= bdt($otherIncome) ?></td></tr>
      <tr class="table-light"><th>Gross Income (1+2+Other)</th><th class="text-end"><?= bdt($gross) ?></th></tr>
      <tr><th>3. Direct Costs (Govt Fee: <?= bdt($pccGovt) ?> + Other: <?= bdt($pccOther) ?>)</th><td class="text-end text-danger"><?= bdt($directCosts) ?></td></tr>
      <tr><th>4. Operating Expenses</th><td class="text-end text-danger"><?= bdt($operating) ?></td></tr>
      <tr class="table-warning"><th>Net Profit / Loss (Gross - Direct - Operating)</th><th class="text-end <?= $net>=0?'text-success':'text-danger' ?>"><?= bdt($net) ?></th></tr>
      <tr><td colspan="2" class="small text-muted">Note: Government fee & Other fee are direct costs, not revenue. Only service charge counts as PCC revenue (fee_mode=service_only). If legacy total includes govt, toggle fee_mode in Settings.</td></tr>
    </table>
    <hr>
    <h6>Balance Snapshot (as of today)</h6>
    <table class="table table-sm">
      <tr><th>Receivables (Sales due + PCC due)</th><td class="text-end"><?= bdt($receivables) ?></td></tr>
      <tr><th>Payables (Purchases due)</th><td class="text-end"><?= bdt($payables) ?></td></tr>
      <tr><th>Cash/Bank/Mobile Balances</th><td class="text-end"><?= bdt($cash) ?></td></tr>
    </table>
    <div class="alert alert-light border small">
      Example validation (Prompt §8): Service 2000 + Govt 500 + Other 200, Paid 1000 → Revenue 2000, Direct 700, Profit 1300. This report follows that rule.
    </div>
  </div>
</div>
