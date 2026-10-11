<?php
$pdo=Database::pdo();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_supplier'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    try{
      $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??'');
      if(!$name||!$phone) throw new Exception("Required");
      $pdo->prepare("INSERT INTO suppliers (name,company_name,phone,email,address,created_by) VALUES (?,?,?,?,?,?)")->execute([$name,trim($_POST['company_name']??''),$phone,trim($_POST['email']??''),trim($_POST['address']??''),$_SESSION['user_id']??null]);
      flash('success','Supplier added'); header("Location: index.php?page=suppliers"); exit;
    }catch(Exception $e){ $err=$e->getMessage(); }
  }
}
if(isset($_GET['delete'])){ $pdo->prepare("UPDATE suppliers SET deleted_at=NOW() WHERE id=?")->execute([(int)$_GET['delete']]); header("Location: index.php?page=suppliers"); exit; }
$search=trim($_GET['search']??''); $where="WHERE deleted_at IS NULL"; $vals=[];
if($search){ $where.=" AND (name LIKE ? OR phone LIKE ?)"; $vals=["%$search%","%$search%"]; }
$rows=$pdo->prepare("SELECT * FROM suppliers $where ORDER BY id DESC LIMIT 100"); $rows->execute($vals); $rows=$rows->fetchAll();
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2"><input type="hidden" name="page" value="suppliers"><input name="search" class="form-control" placeholder="Search" value="<?= e($search) ?>"><button class="btn btn-primary">Search</button></form>
    </div>
    <div class="card">
      <div class="card-header">Suppliers</div>
      <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Phone</th><th>Payable</th><th>Action</th></tr></thead><tbody>
        <?php foreach($rows as $s):
          $due=$pdo->prepare("SELECT COALESCE(SUM(due),0) FROM purchases WHERE supplier_id=? AND deleted_at IS NULL"); $due->execute([$s['id']]); $d=(float)$due->fetchColumn();
        ?>
        <tr><td><?= e($s['name']) ?><br><small class="text-muted"><?= e($s['company_name']) ?></small></td><td><?= e($s['phone']) ?></td><td class="<?= $d>0?'text-danger fw-bold':'' ?>"><?= bdt($d) ?></td><td><a href="index.php?page=suppliers_view&id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a> <a href="index.php?page=suppliers&delete=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></a></td></tr>
        <?php endforeach; if(!$rows): ?><tr><td colspan="4" class="text-center text-muted">No suppliers</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Add Supplier</div>
      <div class="card-body">
        <form method="POST"><?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Name *</label><input name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Company</label><input name="company_name" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Phone *</label><input name="phone" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Email</label><input name="email" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Address</label><textarea name="address" class="form-control"></textarea></div>
          <button name="add_supplier" value="1" class="btn btn-primary w-100">Add</button>
        </form>
      </div>
    </div>
  </div>
</div>
