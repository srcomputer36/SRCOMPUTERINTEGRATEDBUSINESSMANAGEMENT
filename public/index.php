<?php
// Front controller
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Helpers/Security.php';
require_once __DIR__ . '/../app/Helpers/Functions.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Services/AccountingService.php';
require_once __DIR__ . '/../app/Services/PccService.php';

$cfg = cfg();
Security::startSession($cfg);
Database::config($cfg);

// Ensure DB exists? If not, try to create tables error handling
try { Database::pdo()->query("SELECT 1"); } catch (Exception $e) {
    // Show installer hint
    http_response_code(500);
    echo "<h3>Database connection failed</h3><p>".e($e->getMessage())."</p><p>Check config/config.php and run: <code>mysql -u user -p < database/schema.sql</code></p>";
    exit;
}

Auth::requireLogin();

// Router
$page = $_GET['page'] ?? 'dashboard';
$allowed = [
 'dashboard'=>'pages/dashboard.php',
 'pcc_dashboard'=>'pages/pcc/dashboard.php',
 'pcc_list'=>'pages/pcc/list.php',
 'pcc_view'=>'pages/pcc/view.php',
 'pcc_receive'=>'pages/pcc/receive.php',
 'pcc_edit'=>'pages/pcc/edit.php',
 'pcc_reports'=>'pages/pcc/reports.php',
 'business_dashboard'=>'pages/business/dashboard.php',
 'customers'=>'pages/customers/index.php',
 'customers_view'=>'pages/customers/view.php',
 'suppliers'=>'pages/suppliers/index.php',
 'suppliers_view'=>'pages/suppliers/view.php',
 'income_expenses'=>'pages/accounts/income_expenses.php',
 'cash_bank'=>'pages/accounts/cash_bank.php',
 'products'=>'pages/products/index.php',
 'sales'=>'pages/sales/index.php',
 'sales_create'=>'pages/sales/create.php',
 'sales_view'=>'pages/sales/view.php',
 'purchases'=>'pages/purchases/index.php',
 'purchases_create'=>'pages/purchases/create.php',
 'purchases_view'=>'pages/purchases/view.php',
 'accounts'=>'pages/accounts/ledger.php',
 'reports'=>'pages/reports/index.php',
 'reports_pcc'=>'pages/reports/pcc.php',
 'reports_sales'=>'pages/reports/sales.php',
 'reports_stock'=>'pages/reports/stock.php',
 'reports_profit'=>'pages/reports/profit.php',
 'reports_cashbook'=>'pages/reports/cashbook.php',
 'drive'=>'pages/drive/index.php',
 'activity'=>'pages/activity/index.php',
 'settings'=>'pages/settings/index.php',
 'backup'=>'pages/backup/index.php',
];
if (!isset($allowed[$page])) $page='dashboard';
$viewPath = __DIR__ . '/../app/Views/' . $allowed[$page];
$title = ucwords(str_replace('_',' ', $page));

include __DIR__ . '/../app/Views/layout/header.php';
include __DIR__ . '/../app/Views/layout/sidebar.php';
?>
<div class="main">
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <h5 class="mb-0"><?= e($title) ?></h5>
      <span class="badge bg-light text-dark border d-none d-md-inline">Asia/Dhaka • BDT</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="small text-muted d-none d-sm-inline"><?= e($_SESSION['username'] ?? '') ?></span>
      <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>
  <div class="p-3 p-md-4">
    <?php if($msg=flash('success')): ?><div class="alert alert-success alert-auto"><?= e($msg) ?></div><?php endif; ?>
    <?php if($msg=flash('error')): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>
    <?php
    if (file_exists($viewPath)) include $viewPath;
    else echo "<div class='alert alert-warning'>Page not found: ".e($page)."</div>";
    ?>
  </div>
</div>
<?php include __DIR__ . '/../app/Views/layout/footer.php'; ?>
