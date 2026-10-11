<?php
$pdo=Database::pdo();
$suppliers=$pdo->query("SELECT * FROM suppliers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$products=$pdo->query("SELECT * FROM products WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1")->fetchAll();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_purchase'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF";
  else {
    try{
      $supplier_id=$_POST['supplier_id']?:null; $date=$_POST['purchase_date']??date('Y-m-d'); $paid=(float)($_POST['paid']??0); $discount=(float)($_POST['discount']??0);
      $pids=$_POST['product_id']??[]; $qtys=$_POST['qty']??[]; $costs=$_POST['cost']??[];
      if(!$pids) throw new Exception("At least one product");
      $items=[]; $subtotal=0;
      foreach($pids as $i=>$pid){
        $pid=(int)$pid; $qty=(int)($qtys[$i]??0); $cost=(float)($costs[$i]??0);
        if($qty<=0) continue;
        $pr=$pdo->prepare("SELECT * FROM products WHERE id=?"); $pr->execute([$pid]); $pr=$pr->fetch();
        if(!$pr) throw new Exception("Product not found");
        $total=$qty*$cost; $items[]=['product_id'=>$pid,'quantity'=>$qty,'unit_cost'=>$cost,'total'=>$total]; $subtotal+=$total;
      }
      if(!$items) throw new Exception("No valid items");
      $total=$subtotal-$discount; if($total<0) $total=0;
      if($paid>$total) throw new Exception("Paid exceeds total");
      $due=$total-$paid; $status=$due<=0.001?'paid':($paid>0?'partial':'due');
      $invoice=generate_invoice_no($pdo,'purchase');
      $pdo->beginTransaction();
      $pdo->prepare("INSERT INTO purchases (invoice_no,supplier_id,purchase_date,subtotal,discount,total,paid,due,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$invoice,$supplier_id,$date,$subtotal,$discount,$total,$paid,$due,$status,$_SESSION['user_id']??null]);
      $pid=(int)$pdo->lastInsertId();
      foreach($items as $it){
        $pdo->prepare("INSERT INTO purchase_items (purchase_id,product_id,quantity,unit_cost,total) VALUES (?,?,?,?,?)")->execute([$pid,$it['product_id'],$it['quantity'],$it['unit_cost'],$it['total']]);
        $pdo->prepare("UPDATE products SET stock_quantity=stock_quantity+? WHERE id=?")->execute([$it['quantity'],$it['product_id']]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference_type,reference_id,created_by) VALUES (?,?,?,?,?,?)")->execute([$it['product_id'],'purchase',$it['quantity'],'purchase',$pid,$_SESSION['user_id']??null]);
      }
      if($paid>0) $pdo->prepare("INSERT INTO payments (type,purchase_id,supplier_id,amount,payment_date,payment_method,account_id,created_by) VALUES ('purchase',?,?,?,?,?,?,?)")->execute([$pid,$supplier_id,$paid,$date,$_POST['payment_method']??'cash',$_POST['account_id']??null,$_SESSION['user_id']??null]);
      $acct=new AccountingService($pdo);
      $acct->postPurchase($pid,$total,['transaction_date'=>$date,'supplier_id'=>$supplier_id]);
      // if paid, separate payment will be payable clear via cash out — not needed extra now (could post expense payment)
      log_activity($pdo,'create','purchase',$pid,"Purchase $invoice");
      $pdo->commit();
      flash('success',"Purchase $invoice created"); header("Location: index.php?page=purchases_view&id=$pid"); exit;
    }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); $err=$e->getMessage(); }
  }
}
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<form method="POST" class="card">
  <?= Security::csrfField() ?>
  <div class="card-header d-flex justify-content-between"><span>New Purchase Invoice</span><a href="index.php?page=purchases" class="btn btn-sm btn-outline-secondary">Back</a></div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">Select</option><?php foreach($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-md-2"><label class="form-label">Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option></select></div>
      <div class="col-md-3"><label class="form-label">Account</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="table-responsive"><table class="table" id="itemsTableP"><thead><tr><th>Product</th><th>Qty</th><th>Unit Cost</th><th>Total</th><th></th></tr></thead><tbody><tr><td><select name="product_id[]" class="form-select" required><option value="">Select</option><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>" data-cost="<?= $p['purchase_price'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></td><td><input name="qty[]" type="number" min="1" class="form-control qty" value="1" required></td><td><input name="cost[]" type="number" step="0.01" class="form-control cost" value="0" required></td><td class="lineTotal">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger removeRow"><i class="bi bi-x"></i></button></td></tr></tbody></table></div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="addRowP">+ Add Row</button>
    <div class="row g-2 mt-3"><div class="col-md-3"><label class="form-label">Discount</label><input name="discount" type="number" step="0.01" class="form-control" value="0" id="discP"></div><div class="col-md-3"><label class="form-label">Paid</label><input name="paid" type="number" step="0.01" class="form-control" value="0"></div><div class="col-md-6 text-end"><div>Subtotal: <strong id="subP">0.00</strong></div><div>Total: <strong id="totP">0.00</strong></div></div></div>
  </div>
  <div class="card-footer"><button name="create_purchase" value="1" class="btn btn-primary">Create Purchase</button></div>
</form>
<script>
(function(){
  const tb=document.querySelector('#itemsTableP tbody');
  function recalc(){ let sub=0; tb.querySelectorAll('tr').forEach(tr=>{ const q=parseFloat(tr.querySelector('.qty').value||0); const c=parseFloat(tr.querySelector('.cost').value||0); const line=q*c; tr.querySelector('.lineTotal').textContent=line.toFixed(2); sub+=line; }); const d=parseFloat(document.getElementById('discP').value||0); document.getElementById('subP').textContent=sub.toFixed(2); document.getElementById('totP').textContent=(sub-d).toFixed(2); }
  tb.addEventListener('input', e=>{ if(e.target.matches('.qty,.cost')) recalc(); if(e.target.matches('select')){ const cost=e.target.selectedOptions[0]?.dataset.cost||0; e.target.closest('tr').querySelector('.cost').value=cost; recalc(); }});
  document.getElementById('discP').addEventListener('input', recalc);
  document.getElementById('addRowP').addEventListener('click', ()=>{ const r=tb.querySelector('tr').cloneNode(true); r.querySelectorAll('input').forEach(i=>i.value=i.classList.contains('qty')?1:0); r.querySelector('.lineTotal').textContent='0.00'; tb.appendChild(r); });
  tb.addEventListener('click', e=>{ if(e.target.closest('.removeRow') && tb.querySelectorAll('tr').length>1){ e.target.closest('tr').remove(); recalc(); }});
  recalc();
})();
</script>
