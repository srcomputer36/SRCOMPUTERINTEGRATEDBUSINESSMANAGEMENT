<?php
$pdo=Database::pdo();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_product'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    try{
      $name=trim($_POST['name']??''); $sku=trim($_POST['sku']??'');
      if(!$name||!$sku) throw new Exception("Name & SKU required");
      $pdo->prepare("INSERT INTO products (name,sku,category,unit,purchase_price,selling_price,stock_quantity,opening_stock,low_stock_threshold) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$name,$sku,trim($_POST['category']??''),$_POST['unit']??'pcs',(float)$_POST['purchase_price'],(float)$_POST['selling_price'],(int)$_POST['stock_quantity'],(int)$_POST['stock_quantity'],(int)$_POST['low_stock_threshold']]);
      $pid=$pdo->lastInsertId();
      $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,notes,created_by) VALUES (?,'opening',?,?,?)")->execute([$pid,(int)$_POST['stock_quantity'],'Opening stock',$_SESSION['user_id']??null]);
      flash('success','Product added'); header("Location: index.php?page=products"); exit;
    }catch(Exception $e){ $err=$e->getMessage(); if(strpos($err,'uq_sku')!==false) $err="SKU already exists"; }
  }
}
if(isset($_GET['delete'])){ $pdo->prepare("UPDATE products SET deleted_at=NOW() WHERE id=?")->execute([(int)$_GET['delete']]); header("Location:index.php?page=products"); exit; }
// stock adjustment
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['adjust'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    $pid=(int)$_POST['product_id']; $qty=(int)$_POST['qty']; $type=$_POST['adj_type'];
    $prod=$pdo->prepare("SELECT * FROM products WHERE id=?"); $prod->execute([$pid]); $prod=$prod->fetch();
    if($prod){
      $newQty = $type==='in' ? (int)$prod['stock_quantity']+$qty : (int)$prod['stock_quantity']-$qty;
      if($newQty<0) $err="Stock cannot be negative";
      else {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE products SET stock_quantity=? WHERE id=?")->execute([$newQty,$pid]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,notes,created_by) VALUES (?,?,?,?,?)")->execute([$pid,'adjustment', $type==='in'?$qty:-$qty, trim($_POST['notes']??''), $_SESSION['user_id']??null]);
        $pdo->commit();
        flash('success','Stock adjusted'); header("Location:index.php?page=products"); exit;
      }
    }
  }
}
$search=trim($_GET['search']??'');
$where="WHERE deleted_at IS NULL"; $vals=[];
if($search){ $where.=" AND (name LIKE ? OR sku LIKE ? OR category LIKE ?)"; $vals=["%$search%","%$search%","%$search%"]; }
$rows=$pdo->prepare("SELECT * FROM products $where ORDER BY stock_quantity ASC LIMIT 100"); $rows->execute($vals); $rows=$rows->fetchAll();
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="filter-bar d-flex gap-2"><form method="GET" class="d-flex gap-2 flex-grow-1"><input type="hidden" name="page" value="products"><input name="search" class="form-control" placeholder="Search product/sku" value="<?= e($search) ?>"><button class="btn btn-primary">Search</button></form></div>
    <div class="card">
      <div class="card-header d-flex justify-content-between"><span>Products & Inventory (<?= count($rows) ?>)</span><span class="small text-muted">Low stock highlighted</span></div>
      <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Purchase</th><th>Selling</th><th>Stock</th><th>Action</th></tr></thead><tbody>
        <?php foreach($rows as $p):
          $low=(int)$p['stock_quantity'] <= (int)$p['low_stock_threshold'];
        ?>
        <tr class="<?= $low?'table-warning':'' ?>">
          <td><?= e($p['sku']) ?></td><td><?= e($p['name']) ?><br><small class="text-muted"><?= e($p['unit']) ?></small></td><td><?= e($p['category']?:'—') ?></td><td><?= bdt($p['purchase_price']) ?></td><td><?= bdt($p['selling_price']) ?></td><td><span class="badge <?= $low?'bg-danger':'bg-success' ?>"><?= (int)$p['stock_quantity'] ?></span> <?php if($low): ?><i class="bi bi-exclamation-triangle text-danger"></i><?php endif; ?></td><td><a href="index.php?page=products&delete=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></a></td>
        </tr>
        <?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center text-muted">No products</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <div class="card mt-3">
      <div class="card-header">Stock Adjustment</div>
      <div class="card-body">
        <form method="POST" class="row g-2">
          <?= Security::csrfField() ?>
          <div class="col-md-4"><label class="form-label">Product</label><select name="product_id" class="form-select" required><option value="">Select</option><?php foreach($rows as $pr): ?><option value="<?= (int)$pr['id'] ?>"><?= e($pr['name']) ?> (<?= (int)$pr['stock_quantity'] ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label">Type</label><select name="adj_type" class="form-select"><option value="in">Stock In</option><option value="out">Stock Out</option></select></div>
          <div class="col-md-2"><label class="form-label">Quantity</label><input name="qty" type="number" class="form-control" required min="1"></div>
          <div class="col-md-4"><label class="form-label">Notes</label><input name="notes" class="form-control"></div>
          <div class="col-12"><button name="adjust" value="1" class="btn btn-warning">Adjust Stock</button></div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Add Product</div>
      <div class="card-body">
        <form method="POST">
          <?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label">Name *</label><input name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">SKU *</label><input name="sku" class="form-control" required placeholder="SKU-001"></div>
          <div class="mb-2"><label class="form-label">Category</label><input name="category" class="form-control" placeholder="Stationery"></div>
          <div class="row g-2">
            <div class="col-6 mb-2"><label class="form-label">Purchase Price</label><input name="purchase_price" type="number" step="0.01" class="form-control" required value="0"></div>
            <div class="col-6 mb-2"><label class="form-label">Selling Price</label><input name="selling_price" type="number" step="0.01" class="form-control" required value="0"></div>
          </div>
          <div class="row g-2">
            <div class="col-6 mb-2"><label class="form-label">Opening Stock</label><input name="stock_quantity" type="number" class="form-control" value="0"></div>
            <div class="col-6 mb-2"><label class="form-label">Low Threshold</label><input name="low_stock_threshold" type="number" class="form-control" value="5"></div>
          </div>
          <div class="mb-2"><label class="form-label">Unit</label><select name="unit" class="form-select"><option value="pcs">pcs</option><option value="box">box</option><option value="kg">kg</option><option value="rim">rim</option></select></div>
          <button name="add_product" value="1" class="btn btn-primary w-100">Add Product</button>
        </form>
      </div>
    </div>
  </div>
</div>
