<?php
$current = $_GET['page'] ?? 'dashboard';
function isActive($pages,$cur){ return in_array($cur,(array)$pages)?'active':''; }
function groupActive($prefix,$cur){ return str_starts_with($cur,$prefix)?'active':''; }
$userName = e($_SESSION['username'] ?? 'Admin');
?>
<nav id="sidebar" class="sidebar">
  <div class="brand">
    <div class="brand-icon">SR</div>
    <div style="line-height:1.2">
      <div style="font-weight:800;font-size:14px">SR Computer</div>
      <div style="font-size:10px;color:#94a3b8;letter-spacing:.06em">INTEGRATED BUSINESS</div>
    </div>
    <span style="margin-left:auto;background:#1e293b;padding:3px 7px;border-radius:999px;font-size:10px">v2</span>
  </div>
  <div style="padding:12px">
    <!-- Main -->
    <div class="nav-section">প্রধান — Main</div>
    <a class="nav-link <?= isActive(['dashboard'],$current) ?>" href="index.php?page=dashboard"><i class="bi bi-grid"></i> ড্যাশবোর্ড</a>

    <!-- PCC Group -->
    <div class="nav-section" style="cursor:pointer" onclick="document.getElementById('grp-pcc').classList.toggle('d-none')">
      পুলিশ ক্লিয়ারেন্স <i class="bi bi-chevron-down" style="font-size:10px;float:right;margin-top:4px"></i>
    </div>
    <div id="grp-pcc">
      <a class="nav-link <?= isActive(['pcc_dashboard'],$current) ?>" href="index.php?page=pcc_dashboard"><i class="bi bi-shield-check"></i> PCC Overview</a>
      <a class="nav-link <?= isActive(['pcc_list','pcc_view','pcc_edit'],$current) ?>" href="index.php?page=pcc_list"><i class="bi bi-folder2-open"></i> সকল কেস</a>
      <a class="nav-link <?= isActive(['pcc_receive'],$current) ?>" href="index.php?page=pcc_receive"><i class="bi bi-plus-circle"></i> নতুন কেস</a>
      <a class="nav-link <?= isActive(['pcc_reports','reports_pcc'],$current) ?>" href="index.php?page=pcc_reports"><i class="bi bi-file-earmark-bar-graph"></i> PCC রিপোর্ট</a>
    </div>

    <!-- Business Group -->
    <div class="nav-section" style="cursor:pointer" onclick="document.getElementById('grp-biz').classList.toggle('d-none')">
      ব্যবসা — Business <i class="bi bi-chevron-down" style="font-size:10px;float:right;margin-top:4px"></i>
    </div>
    <div id="grp-biz">
      <a class="nav-link <?= isActive(['business_dashboard'],$current) ?>" href="index.php?page=business_dashboard"><i class="bi bi-graph-up"></i> Business Dashboard</a>
      <a class="nav-link <?= isActive(['customers','customers_view'],$current) ?>" href="index.php?page=customers"><i class="bi bi-people"></i> কাস্টমার</a>
      <a class="nav-link <?= isActive(['suppliers','suppliers_view'],$current) ?>" href="index.php?page=suppliers"><i class="bi bi-truck"></i> সাপ্লায়ার</a>
      <a class="nav-link <?= isActive(['products'],$current) ?>" href="index.php?page=products"><i class="bi bi-box-seam"></i> প্রোডাক্ট & স্টক</a>
      <a class="nav-link <?= isActive(['sales','sales_create','sales_view'],$current) ?>" href="index.php?page=sales"><i class="bi bi-receipt"></i> বিক্রয়</a>
      <a class="nav-link <?= isActive(['purchases','purchases_create','purchases_view'],$current) ?>" href="index.php?page=purchases"><i class="bi bi-bag-plus"></i> ক্রয়</a>
      <a class="nav-link <?= isActive(['cash_bank'],$current) ?>" href="index.php?page=cash_bank"><i class="bi bi-bank"></i> ক্যাশ & ব্যাংক</a>
      <a class="nav-link <?= isActive(['income_expenses'],$current) ?>" href="index.php?page=income_expenses"><i class="bi bi-cash-stack"></i> আয়-ব্যয়</a>
    </div>

    <!-- System Group -->
    <div class="nav-section" style="cursor:pointer" onclick="document.getElementById('grp-sys').classList.toggle('d-none')">
      হিসাব & সিস্টেম <i class="bi bi-chevron-down" style="font-size:10px;float:right;margin-top:4px"></i>
    </div>
    <div id="grp-sys">
      <a class="nav-link <?= isActive(['accounts','reports','reports_cashbook','reports_stock','reports_sales','reports_profit'],$current) ?>" href="index.php?page=accounts"><i class="bi bi-journal-text"></i> লেজার</a>
      <a class="nav-link <?= isActive(['reports'],$current) ?>" href="index.php?page=reports"><i class="bi bi-bar-chart-line"></i> রিপোর্টস</a>
      <a class="nav-link <?= isActive(['drive'],$current) ?>" href="index.php?page=drive"><i class="bi bi-google"></i> ফাইলস</a>
      <a class="nav-link <?= isActive(['activity'],$current) ?>" href="index.php?page=activity"><i class="bi bi-clock-history"></i> Activity Log</a>
      <a class="nav-link <?= isActive(['settings'],$current) ?>" href="index.php?page=settings"><i class="bi bi-gear"></i> সেটিংস</a>
      <a class="nav-link <?= isActive(['backup'],$current) ?>" href="index.php?page=backup"><i class="bi bi-cloud-arrow-down"></i> ব্যাকআপ</a>
    </div>

    <div style="margin-top:16px;padding:12px;background:#1e293b;border-radius:12px">
      <div style="display:flex;align-items:center;gap:8px">
        <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#06b6d4);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px"><?= strtoupper(substr($userName,0,2)) ?></div>
        <div><div style="font-size:12px;font-weight:600"><?= $userName ?></div><div style="font-size:10px;color:#94a3b8">অ্যাডমিন</div></div>
      </div>
      <a href="logout.php" style="display:block;margin-top:10px;background:#4f46e5;color:#fff;text-align:center;padding:7px;border-radius:8px;font-size:12px;font-weight:600"><i class="bi bi-box-arrow-right"></i> লগআউট</a>
    </div>
  </div>
</nav>
