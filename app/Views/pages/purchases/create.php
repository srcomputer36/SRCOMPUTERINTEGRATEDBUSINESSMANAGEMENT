<?php
// Simplified Purchase — nested-safe + easier
$pdo=Database::pdo();
$suppliers=$pdo->query("SELECT id,name FROM suppliers WHERE deleted_at IS NULL ORDER BY name LIMIT 100")->fetchAll();
$products=$pdo->query("SELECT id,name,sku,purchase_price,stock_quantity FROM products WHERE deleted_at IS NULL ORDER BY name LIMIT 100")->fetchAll();
$accounts=$pdo->query("SELECT id,name FROM accounts WHERE is_active=1 ORDER BY id")->fetchAll();
$err=null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_purchase'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="Security token ভুল";
  else {
    try{
      $supplier_id=!empty($_POST['supplier_id'])?(int)$_POST['supplier_id']:null;
      $date=$_POST['purchase_date']??date('Y-m-d');
      if(!strtotime($date)) $date=date('Y-m-d');
      $paid=(float)($_POST['paid']??0); $discount=(float)($_POST['discount']??0);
      if($paid<0) $paid=0; if($discount<0) $discount=0;
      $pids=$_POST['product_id']??[]; $qtys=$_POST['qty']??[]; $costs=$_POST['cost']??[];
      if(empty($pids) || count(array_filter($pids))==0) throw new Exception("অন্তত একটি প্রোডাক্ট নির্বাচন করুন");
      $items=[]; $subtotal=0;
      foreach($pids as $i=>$pid){
        $pid=(int)$pid; if($pid<=0) continue;
        $qty=(int)($qtys[$i]??0); $cost=(float)($costs[$i]??0);
        if($qty<=0) throw new Exception("পরিমাণ 1+ হতে হবে");
        $pr=$pdo->prepare("SELECT id,name FROM products WHERE id=? AND deleted_at IS NULL"); $pr->execute([$pid]); $pr=$pr->fetch();
        if(!$pr) throw new Exception("প্রোডাক্ট পাওয়া যায়নি");
        $line=$qty*$cost;
        $items[]=['product_id'=>$pid,'quantity'=>$qty,'unit_cost'=>$cost,'total'=>$line];
        $subtotal+=$line;
      }
      if(!$items) throw new Exception("কোনো সঠিক আইটেম নেই");
      $total=max(0,$subtotal-$discount);
      if($paid>$total) throw new Exception("জমা ৳$paid মোট ৳$total এর বেশি");
      $due=$total-$paid; $status=$due<=0.01?'paid':($paid>0?'partial':'due');

      $pdo->beginTransaction();
      $invoice=generate_invoice_no($pdo,'purchase');
      $pdo->prepare("INSERT INTO purchases (invoice_no,supplier_id,purchase_date,subtotal,discount,total,paid,due,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
          ->execute([$invoice,$supplier_id,$date,$subtotal,$discount,$total,$paid,$due,$status,$_SESSION['user_id']??null]);
      $pid=(int)$pdo->lastInsertId();
      foreach($items as $it){
        $pdo->prepare("INSERT INTO purchase_items (purchase_id,product_id,quantity,unit_cost,total) VALUES (?,?,?,?,?)")
            ->execute([$pid,$it['product_id'],$it['quantity'],$it['unit_cost'],$it['total']]);
        $pdo->prepare("UPDATE products SET stock_quantity=stock_quantity+? WHERE id=?")->execute([$it['quantity'],$it['product_id']]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference_type,reference_id,created_by) VALUES (?,?,?,?,?,?)")
            ->execute([$it['product_id'],'purchase',$it['quantity'],'purchase',$pid,$_SESSION['user_id']??null]);
      }
      if($paid>0){
        $pdo->prepare("INSERT INTO payments (type,purchase_id,supplier_id,amount,payment_date,payment_method,account_id,created_by) VALUES ('purchase',?,?,?,?,?,?,?)")
            ->execute([$pid,$supplier_id,$paid,$date,$_POST['payment_method']??'cash',$_POST['account_id']??null,$_SESSION['user_id']??null]);
        if(!empty($_POST['account_id'])) $pdo->prepare("UPDATE accounts SET balance=balance-? WHERE id=?")->execute([$paid,(int)$_POST['account_id']]);
      }
      $acct=new AccountingService($pdo);
      $acct->postPurchase($pid,$total,['transaction_date'=>$date,'supplier_id'=>$supplier_id]);
      log_activity($pdo,'create','purchase',$pid,"Purchase $invoice ৳$total");
      $pdo->commit();
      flash('success',"ক্রয় সফল! ইনভয়েস: $invoice");
      header("Location: index.php?page=purchases_view&id=$pid"); exit;
    }catch(Exception $e){
      if($pdo->inTransaction()) $pdo->rollBack();
      $err=$e->getMessage();
    }
  }
}
$noProducts=empty($products);
?>
<?php if($noProducts): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> প্রোডাক্ট নেই — <a href="index.php?page=products">প্রোডাক্ট যোগ করুন</a></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?= e($err) ?></div><?php endif; ?>
<form method="POST" class="card">
  <?= Security::csrfField() ?>
  <div class="card-header d-flex justify-content-between"><span><i class="bi bi-bag-plus" style="color:var(--primary)"></i> নতুন ক্রয় — সহজ ৩ ধাপ</span><span class="small text-muted">স্টক auto-বাড়বে</span></div>
  <div class="card-body">
    <h6 class="fw-bold" style="color:var(--primary)"><span style="background:var(--primary);color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px">1</span> সাপ্লায়ার ও তারিখ</h6>
    <div class="row g-3 mb-3">
      <div class="col-md-5"><label class="form-label">সাপ্লায়ার — ঐচ্ছিক</label><select name="supplier_id" class="form-select"><option value="">Select / Walk-in Supplier</option><?php foreach($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">তারিখ *</label><input type="date" name="purchase_date" class="form-control" required value="<?= e($_POST['purchase_date']??date('Y-m-d')) ?>"></div>
      <div class="col-md-2"><label class="form-label">পেমেন্ট</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option></select></div>
      <div class="col-md-2"><label class="form-label">অ্যাকাউন্ট</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    </div>

    <h6 class="fw-bold" style="color:var(--primary)"><span style="background:var(--primary);color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px">2</span> প্রোডাক্ট</h6>
    <div class="table-responsive"><table class="table align-middle" id="itemsTableP"><thead><tr><th>প্রোডাক্ট *</th><th style="width:110px">পরিমাণ</th><th style="width:130px">ক্রয় দাম</th><th style="width:120px">টোটাল</th><th></th></tr></thead><tbody><tr><td><select name="product_id[]" class="form-select" required><option value="">— নির্বাচন —</option><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>" data-cost="<?= $p['purchase_price'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option><?php endforeach; ?></select></td><td><input name="qty[]" type="number" min="1" class="form-control qty" value="1" required></td><td><input name="cost[]" type="number" step="0.01" class="form-control cost" value="0" required></td><td class="lineTotal fw-bold">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger removeRow"><i class="bi bi-trash"></i></button></td></tr></tbody></table></div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="addRowP"><i class="bi bi-plus-lg"></i> আরও প্রোডাক্ট</button>

    <div class="card mt-3" style="background:#f8fafc;border:1px dashed var(--border)"><div class="card-body">
      <h6 class="fw-bold" style="color:var(--primary)"><span style="background:var(--primary);color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px">3</span> হিসাব</h6>
      <div class="row g-3 align-items-end"><div class="col-md-3"><label class="form-label">ছাড়</label><input name="discount" type="number" step="0.01" min="0" class="form-control" value="0" id="discP"></div><div class="col-md-3"><label class="form-label">জমা</label><input name="paid" type="number" step="0.01" min="0" class="form-control" value="0" id="paidP"></div><div class="col-md-6"><div class="bg-white border rounded-3 p-3"><div class="d-flex justify-content-between"><span>Subtotal</span><strong id="subP">0.00</strong></div><div class="d-flex justify-content-between"><span>মোট</span><strong id="totP" style="font-size:18px;color:var(--primary)">0.00</strong></div><div class="d-flex justify-content-between"><span>বাকি</span><strong id="dueP" class="text-danger">0.00</strong></div></div></div></div>
    </div>
  </div>
  <div class="card-footer d-flex gap-2"><button name="create_purchase" value="1" class="btn btn-primary"><i class="bi bi-check-lg"></i> ক্রয় সেভ</button><a href="index.php?page=purchases" class="btn btn-ghost">বাতিল</a></div>
</form>
<script>
(function(){
  const tb=document.querySelector('#itemsTableP tbody');
  const disc=document.getElementById('discP'), paid=document.getElementById('paidP');
  function recalc(){ let sub=0; tb.querySelectorAll('tr').forEach(tr=>{ const q=parseFloat(tr.querySelector('.qty').value||0)||0; const c=parseFloat(tr.querySelector('.cost').value||0)||0; const line=q*c; tr.querySelector('.lineTotal').textContent=line.toFixed(2); sub+=line; }); const d=parseFloat(disc.value||0)||0; const p=parseFloat(paid.value||0)||0; const tot=Math.max(0,sub-d); document.getElementById('subP').textContent=sub.toFixed(2); document.getElementById('totP').textContent=tot.toFixed(2); document.getElementById('dueP').textContent=Math.max(0,tot-p).toFixed(2); }
  tb.addEventListener('input', e=>{ if(e.target.matches('.qty,.cost')) recalc(); });
  tb.addEventListener('change', e=>{ if(e.target.matches('select')){ const c=e.target.selectedOptions[0]?.dataset.cost||0; e.target.closest('tr').querySelector('.cost').value=c; recalc(); }});
  disc.addEventListener('input', recalc); paid.addEventListener('input', recalc);
  document.getElementById('addRowP').addEventListener('click', ()=>{ const r=tb.querySelector('tr').cloneNode(true); r.querySelectorAll('input').forEach(i=>i.value=i.classList.contains('qty')?1:0); r.querySelector('select').selectedIndex=0; r.querySelector('.lineTotal').textContent='0.00'; tb.appendChild(r); });
  tb.addEventListener('click', e=>{ if(e.target.closest('.removeRow') && tb.querySelectorAll('tr').length>1){ e.target.closest('tr').remove(); recalc(); }});
  recalc();
})();
</script>
