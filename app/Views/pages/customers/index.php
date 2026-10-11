<?php
$pdo=Database::pdo();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_customer'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF invalid";
  else {
    try{
      $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??'');
      if(!$name||!$phone) throw new Exception("Name and phone required");
      if(!preg_match('/^01[3-9]\d{8}$/',$phone)) throw new Exception("Invalid phone");
      $pdo->prepare("INSERT INTO customers (name,phone,email,address,opening_balance,created_by) VALUES (?,?,?,?,?,?)")->execute([$name,$phone,trim($_POST['email']??''),trim($_POST['address']??''),(float)($_POST['opening_balance']??0),$_SESSION['user_id']??null]);
      log_activity($pdo,'create','customer',$pdo->lastInsertId(),"Customer $name");
      flash('success','Customer added'); header("Location: index.php?page=customers"); exit;
    }catch(Exception $e){ $err=$e->getMessage(); if(strpos($err,'Duplicate')!==false||strpos($err,'uq_phone')!==false) $err="Phone already exists"; }
  }
}
if(isset($_GET['delete'])){
  if(!Security::verifyCsrf($_GET['_csrf']??'')) $err="CSRF";
  else { $pdo->prepare("UPDATE customers SET deleted_at=NOW() WHERE id=?")->execute([(int)$_GET['delete']]); flash('success','Deleted'); header("Location: index.php?page=customers"); exit; }
}
$search=trim($_GET['search']??'');
$where="WHERE deleted_at IS NULL"; $vals=[];
if($search){ $where.=" AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)"; $vals=["%$search%","%$search%","%$search%"]; }
$rows=$pdo->prepare("SELECT * FROM customers $where ORDER BY created_at DESC LIMIT 100");
$rows->execute($vals); $rows=$rows->fetchAll();
// compute due per customer: sales due + pcc due
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="filter-bar d-flex gap-2">
      <form method="GET" class="d-flex gap-2 flex-grow-1"><input type="hidden" name="page" value="customers"><input name="search" class="form-control" placeholder="Search name/phone" value="<?= e($search) ?>"><button class="btn btn-primary">Search</button></form>
    </div>
    <div class="card">
      <div class="card-header">Customers (<?= count($rows) ?>)</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Name</th><th>Phone</th><th>Balance</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach($rows as $c):
            // ledger: sum due
            $saleDue=$pdo->prepare("SELECT COALESCE(SUM(due),0) FROM sales WHERE customer_id=? AND deleted_at IS NULL"); $saleDue->execute([$c['id']]); $sd=(float)$saleDue->fetchColumn();
            $pccDue=$pdo->prepare("SELECT COALESCE(SUM(due_amount),0) FROM pcc_cases WHERE customer_id=? AND deleted_at IS NULL"); $pccDue->execute([$c['id']]); $pd=(float)$pccDue->fetchColumn();
            $totalDue=$sd+$pd;
          ?>
            <tr>
              <td><a href="index.php?page=customers_view&id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a><br><small class="text-muted"><?= e($c['email']?:'') ?></small></td>
              <td><?= e($c['phone']) ?></td>
              <td class="<?= $totalDue>0?'text-danger fw-bold':'' ?>"><?= bdt($totalDue) ?> <small class="text-muted">due</small></td>
              <td><a href="index.php?page=customers_view&id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a> <a href="index.php?page=customers&delete=<?= (int)$c['id'] ?>&_csrf=<?= e(Security::csrfToken()) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></a></td>
            </tr>
          <?php endforeach; if(!$rows): ?><tr><td colspan="4" class="text-center text-muted p-3">No customers</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Add Customer</div>
      <div class="card-body">
        <form method="POST">
          <?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Name *</label><input name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Phone *</label><input name="phone" class="form-control" required placeholder="017..."></div>
          <div class="mb-2"><label class="form-label">Email</label><input name="email" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Address</label><textarea name="address" class="form-control"></textarea></div>
          <div class="mb-2"><label class="form-label">Opening Balance</label><input name="opening_balance" type="number" step="0.01" class="form-control" value="0"></div>
          <button name="add_customer" value="1" class="btn btn-primary w-100">Add Customer</button>
        </form>
      </div>
    </div>
  </div>
</div>
