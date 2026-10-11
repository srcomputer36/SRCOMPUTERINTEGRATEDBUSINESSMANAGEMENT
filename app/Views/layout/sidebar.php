<?php
$current = $_GET['page'] ?? 'dashboard';
function nav_active($page, $current){ return $page===$current ? 'active' : ''; }
function nav_has($prefix, $current){ return str_starts_with($current, $prefix) ? 'active' : ''; }
$userName = e($_SESSION['username'] ?? 'Admin');
?>
<nav id="sidebar" class="sidebar">
  <div class="brand">
    <i class="bi bi-building"></i> <?= e(cfg('app.name') ?? 'SR Computer') ?>
  </div>
  <div class="px-2 py-2">
    <!-- Main -->
    <div class="nav-section">Main</div>
    <a class="nav-link <?= nav_active('dashboard',$current) ?>" href="index.php?page=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link <?= nav_active('pcc_dashboard',$current) ?>" href="index.php?page=pcc_dashboard"><i class="bi bi-shield-check"></i> PCC Overview</a>

    <div class="nav-section">Police Clearance</div>
    <a class="nav-link <?= nav_has('pcc',$current) && $current!=='pcc_dashboard' ? '' : '' ?> <?= in_array($current,['pcc_list','pcc_view','pcc_receive','pcc_edit'])?'active':'' ?>" href="index.php?page=pcc_list"><i class="bi bi-folder2-open"></i> All Cases / Records</a>
    <a class="nav-link <?= nav_active('pcc_receive',$current) ?>" href="index.php?page=pcc_receive"><i class="bi bi-plus-circle"></i> Receive New Case</a>
    <a class="nav-link <?= nav_active('pcc_reports',$current) ?>" href="index.php?page=pcc_reports"><i class="bi bi-file-earmark-bar-graph"></i> PCC Reports</a>

    <div class="nav-section">Business Management</div>
    <a class="nav-link <?= nav_active('business_dashboard',$current) ?>" href="index.php?page=business_dashboard"><i class="bi bi-graph-up"></i> Business Dashboard</a>
    <a class="nav-link <?= nav_active('customers',$current) ?>" href="index.php?page=customers"><i class="bi bi-people"></i> Customers</a>
    <a class="nav-link <?= nav_active('suppliers',$current) ?>" href="index.php?page=suppliers"><i class="bi bi-truck"></i> Suppliers</a>
    <a class="nav-link <?= nav_active('income_expenses',$current) ?>" href="index.php?page=income_expenses"><i class="bi bi-cash-stack"></i> Income & Expenses</a>
    <a class="nav-link <?= nav_active('cash_bank',$current) ?>" href="index.php?page=cash_bank"><i class="bi bi-bank"></i> Cash & Bank</a>
    <a class="nav-link <?= nav_active('products',$current) ?>" href="index.php?page=products"><i class="bi bi-box-seam"></i> Products & Inventory</a>
    <a class="nav-link <?= nav_active('sales',$current) ?>" href="index.php?page=sales"><i class="bi bi-receipt"></i> Sales</a>
    <a class="nav-link <?= nav_active('purchases',$current) ?>" href="index.php?page=purchases"><i class="bi bi-bag-plus"></i> Purchases</a>

    <div class="nav-section">Accounts & System</div>
    <a class="nav-link <?= nav_active('accounts',$current) ?>" href="index.php?page=accounts"><i class="bi bi-journal-text"></i> Accounts & Ledger</a>
    <a class="nav-link <?= nav_active('reports',$current) ?>" href="index.php?page=reports"><i class="bi bi-bar-chart-line"></i> Reports</a>
    <a class="nav-link <?= nav_active('drive',$current) ?>" href="index.php?page=drive"><i class="bi bi-google"></i> Drive / Files</a>
    <a class="nav-link <?= nav_active('activity',$current) ?>" href="index.php?page=activity"><i class="bi bi-clock-history"></i> Activity Log</a>
    <a class="nav-link <?= nav_active('settings',$current) ?>" href="index.php?page=settings"><i class="bi bi-gear"></i> Settings</a>
    <a class="nav-link <?= nav_active('backup',$current) ?>" href="index.php?page=backup"><i class="bi bi-cloud-arrow-down"></i> Backup & Restore</a>

    <div class="mt-4 px-3">
      <div class="d-flex align-items-center gap-2 text-white-50 small">
        <i class="bi bi-person-circle"></i> <?= $userName ?>
      </div>
      <a href="logout.php" class="btn btn-sm btn-outline-light w-100 mt-2"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
    <div class="mt-3 px-3 small text-white-50">
      <div>SR Computer © <?= date('Y') ?></div>
      <div style="opacity:.6">Integrated Business</div>
    </div>
  </div>
</nav>
