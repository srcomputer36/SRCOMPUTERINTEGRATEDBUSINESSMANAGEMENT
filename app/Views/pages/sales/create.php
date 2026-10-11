<?php
$pdo=Database::pdo();
$customers=$pdo->query("SELECT * FROM customers WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$products=$pdo->query("SELECT * FROM products WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1")->fetchAll();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_sale'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="CSRF invalid";
  else {
    try{
      $customer_id=$_POST['customer_id']?:null;
      $sale_date=$_POST['sale_date']??date('Y-m-d');
      $payment_method=$_POST['payment_method']??'cash';
      $account_id=$_POST['account_id']?:null;
      $paid=(float)($_POST['paid']??0);
      $discount=(float)($_POST['discount']??0);
      // items
      $pids=$_POST['product_id']??[]; $qtys=$_POST['qty']??[]; $prices=$_POST['price']??[];
      if(!$pids) throw new Exception("At least one product");
      $items=[];
      $subtotal=0;
      foreach($pids as $i=>$pid){
        $pid=(int)$pid; $qty=(int)($qtys[$i]??0); $price=(float)($prices[$i]??0);
        if($qty<=0) continue;
        // check stock
        $pr=$pdo->prepare("SELECT * FROM products WHERE id=?"); $pr->execute([$pid]); $pr=$pr->fetch();
        if(!$pr) throw new Exception("Product not found");
        if((int)$pr['stock_quantity'] < $qty) throw new Exception("Insufficient stock for ".$pr['name']." (available ".(int)$pr['stock_quantity'].")");
        $total=$qty*$price;
        $items[]=['product_id'=>$pid,'quantity'=>$qty,'unit_price'=>$price,'total'=>$total];
        $subtotal+=$total;
      }
      if(!$items) throw new Exception("No valid items");
      $total=$subtotal - $discount;
      if($total<0) $total=0;
      if($paid>$total) throw new Exception("Paid exceeds total");
      $due=$total - $paid;
      $status=$due<=0.001?'paid':($paid>0?'partial':'due');
      $invoice=generate_invoice_no($pdo,'sale');
      $pdo->beginTransaction();
      $pdo->prepare("INSERT INTO sales (invoice_no,customer_id,sale_date,subtotal,discount,total,paid,due,payment_method,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$invoice,$customer_id,$sale_date,$subtotal,$discount,$total,$paid,$due,$payment_method,$status,$_SESSION['user_id']??null]);
      $saleId=(int)$pdo->lastInsertId();
      foreach($items as $it){
        $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,total) VALUES (?,?,?,?,?)")->execute([$saleId,$it['product_id'],$it['quantity'],$it['unit_price'],$it['total']]);
        // stock movement
        $pdo->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=?")->execute([$it['quantity'],$it['product_id']]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference_type,reference_id,created_by) VALUES (?,?,?,?,?,?)")->execute([$it['product_id'],'sale', -$it['quantity'],'sale',$saleId,$_SESSION['user_id']??null]);
      }
      // payments if paid>0
      if($paid>0){
        $pdo->prepare("INSERT INTO payments (type,sale_id,customer_id,amount,payment_date,payment_method,account_id,created_by) VALUES ('sale',?,?,?,?,?,?,?)")->execute([$saleId,$customer_id,$paid,$sale_date,$payment_method,$account_id,$_SESSION['user_id']??null]);
      }
      // accounting
      $acct=new AccountingService($pdo);
      $acct->postSale($saleId,$total,['transaction_date'=>$sale_date,'customer_id'=>$customer_id]);
      if($paid>0) $acct->postSalePayment($saleId,$paid,['transaction_date'=>$sale_date,'account_id'=>$account_id,'payment_method'=>$payment_method,'customer_id'=>$customer_id]);
      log_activity($pdo,'create','sale',$saleId,"Sale $invoice");
      $pdo->commit();
      flash('success',"Sale created: $invoice"); header("Location: index.php?page=sales_view&id=$saleId"); exit;
    }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); $err=$e->getMessage(); }
  }
}
?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<form method="POST" class="card">
  <?= Security::csrfField() ?>
  <div class="card-header d-flex justify-content-between"><span><i class="bi bi-receipt"></i> New Sales Invoice</span><a href="index.php?page=sales" class="btn btn-sm btn-outline-secondary">Back</a></div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-4"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">Walk-in / Select</option><?php foreach($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['phone']) ?>)</option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Date *</label><input type="date" name="sale_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
      <div class="col-md-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="bank">Bank</option></select></div>
      <div class="col-md-3"><label class="form-label">Account</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="table-responsive">
      <table class="table" id="itemsTable">
        <thead><tr><th>Product</th><th>Available</th><th>Qty</th><th>Unit Price</th><th>Total</th><th></th></tr></thead>
        <tbody>
          <tr>
            <td><select name="product_id[]" class="form-select" required><option value="">Select</option><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= (int)$p['stock_quantity'] ?>"><?= e($p['name']) ?> — <?= e($p['sku']) ?></option><?php endforeach; ?></select></td>
            <td class="stock">-</td>
            <td><input name="qty[]" type="number" min="1" class="form-control qty" value="1" required></td>
            <td><input name="price[]" type="number" step="0.01" class="form-control price" value="0" required></td>
            <td class="lineTotal">0.00</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger removeRow"><i class="bi bi-x"></i></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="addRow"><i class="bi bi-plus"></i> Add Row</button>
    <div class="row g-2 mt-3">
      <div class="col-md-3"><label class="form-label">Discount</label><input name="discount" type="number" step="0.01" class="form-control" value="0" id="discount"></div>
      <div class="col-md-3"><label class="form-label">Paid Amount</label><input name="paid" type="number" step="0.01" class="form-control" value="0"></div>
      <div class="col-md-6 text-end">
        <div>Subtotal: <strong id="subtotal">0.00</strong></div>
        <div>Total: <strong id="total">0.00</strong></div>
        <div class="small text-muted">Due auto-calculated</div>
      </div>
    </div>
  </div>
  <div class="card-footer"><button name="create_sale" value="1" class="btn btn-primary">Create Sale</button></div>
</form>
<script>
(function(){
  const table=document.getElementById('itemsTable').querySelector('tbody');
  function recalc(){
    let sub=0;
    table.querySelectorAll('tr').forEach(tr=>{
      const q=parseFloat(tr.querySelector('.qty')?.value||0);
      const p=parseFloat(tr.querySelector('.price')?.value||0);
      const line=q*p;
      tr.querySelector('.lineTotal').textContent=line.toFixed(2);
      sub+=line;
    });
    const disc=parseFloat(document.getElementById('discount').value||0);
    document.getElementById('subtotal').textContent=sub.toFixed(2);
    document.getElementById('total').textContent=(sub-disc).toFixed(2);
  }
  table.addEventListener('input', e=>{
    if(e.target.matches('.qty,.price')) recalc();
    if(e.target.matches('select')){
      const sel=e.target; const opt=sel.selectedOptions[0];
      if(opt){ const price=opt.dataset.price||0; const stock=opt.dataset.stock||'-'; sel.closest('tr').querySelector('.price').value=price; sel.closest('tr').querySelector('.stock').textContent=stock; recalc(); }
    }
  });
  document.getElementById('discount').addEventListener('input', recalc);
  document.getElementById('addRow').addEventListener('click', ()=>{
    const row=table.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input').forEach(i=>i.value=i.classList.contains('qty')?1:0);
    row.querySelector('.stock').textContent='-'; row.querySelector('.lineTotal').textContent='0.00';
    table.appendChild(row);
  });
  table.addEventListener('click', e=>{
    if(e.target.closest('.removeRow') && table.querySelectorAll('tr').length>1){ e.target.closest('tr').remove(); recalc(); }
  });
  recalc();
})();
</script>
