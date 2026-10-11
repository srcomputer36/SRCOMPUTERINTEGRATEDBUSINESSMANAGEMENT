<?php
// Simplified Sales — fixed nested transaction bug + easier UX
$pdo=Database::pdo();
$customers=$pdo->query("SELECT id,name,phone FROM customers WHERE deleted_at IS NULL ORDER BY name LIMIT 100")->fetchAll();
$products=$pdo->query("SELECT id,name,sku,selling_price,stock_quantity FROM products WHERE deleted_at IS NULL ORDER BY name LIMIT 100")->fetchAll();
$accounts=$pdo->query("SELECT id,name FROM accounts WHERE is_active=1 ORDER BY id")->fetchAll();
$err=null; $ok=null;

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_sale'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $err="Security token ভুল — পেজ রিফ্রেশ করুন";
  else {
    try{
      $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
      $sale_date = $_POST['sale_date'] ?? date('Y-m-d');
      if(!strtotime($sale_date)) $sale_date = date('Y-m-d');
      $payment_method = $_POST['payment_method'] ?? 'cash';
      $account_id = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : ($accounts[0]['id'] ?? null);
      $paid=(float)($_POST['paid']??0);
      $discount=(float)($_POST['discount']??0);
      if($discount<0) $discount=0;
      if($paid<0) $paid=0;

      $pids=$_POST['product_id']??[]; $qtys=$_POST['qty']??[]; $prices=$_POST['price']??[];
      if(empty($pids) || count(array_filter($pids))==0) throw new Exception("অন্তত একটি প্রোডাক্ট নির্বাচন করুন");
      
      $items=[]; $subtotal=0;
      foreach($pids as $i=>$pid){
        $pid=(int)$pid; if($pid<=0) continue;
        $qty=(int)($qtys[$i]??0); $price=(float)($prices[$i]??0);
        if($qty<=0) throw new Exception("পরিমাণ 1 বা তার বেশি হতে হবে");
        if($price<0) $price=0;
        $pr=$pdo->prepare("SELECT id,name,stock_quantity FROM products WHERE id=? AND deleted_at IS NULL");
        $pr->execute([$pid]); $pr=$pr->fetch();
        if(!$pr) throw new Exception("প্রোডাক্ট পাওয়া যায়নি (ID $pid)");
        if((int)$pr['stock_quantity'] < $qty) throw new Exception("স্টক পর্যাপ্ত নয় — {$pr['name']} এ আছে ".(int)$pr['stock_quantity']."টি, চেয়েছেন $qtyটি");
        $line=$qty*$price;
        $items[]=['product_id'=>$pid,'quantity'=>$qty,'unit_price'=>$price,'total'=>$line];
        $subtotal+=$line;
      }
      if(!$items) throw new Exception("কোনো সঠিক আইটেম নেই");
      $total=$subtotal - $discount;
      if($total<0) $total=0;
      if($paid > $total) throw new Exception("জমা টাকা (৳$paid) মোট (৳$total) এর বেশি হতে পারবে না");
      $due=$total - $paid;
      $status=$due<=0.01?'paid':($paid>0?'partial':'due');

      // Generate invoice inside transaction to avoid gaps — now nested-safe
      $pdo->beginTransaction();
      $invoice=generate_invoice_no($pdo,'sale');
      $pdo->prepare("INSERT INTO sales (invoice_no,customer_id,sale_date,subtotal,discount,total,paid,due,payment_method,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
          ->execute([$invoice,$customer_id,$sale_date,$subtotal,$discount,$total,$paid,$due,$payment_method,$status,$_SESSION['user_id']??null]);
      $saleId=(int)$pdo->lastInsertId();
      foreach($items as $it){
        $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,total) VALUES (?,?,?,?,?)")
            ->execute([$saleId,$it['product_id'],$it['quantity'],$it['unit_price'],$it['total']]);
        $pdo->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=?")->execute([$it['quantity'],$it['product_id']]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference_type,reference_id,created_by) VALUES (?,?,?,?,?,?)")
            ->execute([$it['product_id'],'sale', -$it['quantity'],'sale',$saleId,$_SESSION['user_id']??null]);
      }
      if($paid>0){
        $pdo->prepare("INSERT INTO payments (type,sale_id,customer_id,amount,payment_date,payment_method,account_id,created_by) VALUES ('sale',?,?,?,?,?,?,?)")
            ->execute([$saleId,$customer_id,$paid,$sale_date,$payment_method,$account_id,$_SESSION['user_id']??null]);
      }
      // Accounting — now nested-safe via fixed generate_txn_no
      $acct=new AccountingService($pdo);
      $acct->postSale($saleId,$total,['transaction_date'=>$sale_date,'customer_id'=>$customer_id]);
      if($paid>0) $acct->postSalePayment($saleId,$paid,['transaction_date'=>$sale_date,'account_id'=>$account_id,'payment_method'=>$payment_method,'customer_id'=>$customer_id]);
      // Update account balance for paid amount
      if($paid>0 && $account_id) $pdo->prepare("UPDATE accounts SET balance=balance+? WHERE id=?")->execute([$paid,$account_id]);
      log_activity($pdo,'create','sale',$saleId,"Sale $invoice ৳$total");
      $pdo->commit();
      flash('success',"বিক্রয় সফল! ইনভয়েস: $invoice — মোট ৳".number_format($total,2));
      header("Location: index.php?page=sales_view&id=$saleId"); exit;
    }catch(Exception $e){
      if($pdo->inTransaction()) $pdo->rollBack();
      $err=$e->getMessage();
    }
  }
}

// Helper: show warnings if no master data
$noProducts = empty($products);
$noCustomers = empty($customers);
?>
<?php if($noProducts): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <strong>প্রোডাক্ট নেই!</strong> প্রথমে <a href="index.php?page=products">প্রোডাক্ট যোগ করুন</a> — নইলে বিক্রয় করা যাবে না। ডেমো প্রোডাক্ট seed এ আছে, DB import করেছেন তো?</div>
<?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?= e($err) ?></div><?php endif; ?>

<form method="POST" class="card" novalidate>
  <?= Security::csrfField() ?>
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-receipt" style="color:var(--primary)"></i> নতুন বিক্রয় — সহজ ৩ ধাপ</span>
    <span class="small text-muted">ইনভয়েস auto • স্টক auto-কমবে • বাকি auto</span>
  </div>
  <div class="card-body">
    <!-- Step 1: Customer & Date -->
    <div class="mb-3">
      <h6 class="fw-bold" style="color:var(--primary)"><span style="background:var(--primary);color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px">1</span> কাস্টমার ও তারিখ</h6>
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">কাস্টমার — ঐচ্ছিক</label>
          <select name="customer_id" class="form-select">
            <option value="">Walk-in (যেকোনো ক্রেতা)</option>
            <?php foreach($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (isset($_POST['customer_id']) && (int)$_POST['customer_id']==(int)$c['id'])?'selected':''?>><?= e($c['name']) ?> — <?= e($c['phone']) ?></option><?php endforeach; ?>
          </select>
          <div class="form-text">না থাকলে Walk-in হিসেবে হবে, পরে কাস্টমার যোগ করতে পারবেন</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">তারিখ *</label>
          <input type="date" name="sale_date" class="form-control" required value="<?= e($_POST['sale_date']??date('Y-m-d')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">পেমেন্ট</label>
          <select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="bank">Bank</option><option value="nagad">Nagad</option></select>
        </div>
        <div class="col-md-2">
          <label class="form-label">অ্যাকাউন্ট</label>
          <select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
        </div>
      </div>
    </div>

    <!-- Step 2: Products -->
    <div class="mb-3">
      <h6 class="fw-bold" style="color:var(--primary)"><span style="background:var(--primary);color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px">2</span> প্রোডাক্ট যোগ করুন</h6>
      <div class="table-responsive">
        <table class="table align-middle" id="itemsTable">
          <thead><tr><th style="min-width:240px">প্রোডাক্ট *</th><th>স্টক</th><th style="width:110px">পরিমাণ</th><th style="width:130px">দাম</th><th style="width:120px">লাইন টোটাল</th><th></th></tr></thead>
          <tbody>
            <tr>
              <td>
                <select name="product_id[]" class="form-select" required>
                  <option value="">— নির্বাচন করুন —</option>
                  <?php foreach($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= (int)$p['stock_quantity'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) — ৳<?= number_format($p['selling_price'],0) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="stock small text-muted">-</td>
              <td><input name="qty[]" type="number" min="1" class="form-control qty" value="1" required></td>
              <td><input name="price[]" type="number" step="0.01" class="form-control price" value="0" required></td>
              <td class="lineTotal fw-bold">0.00</td>
              <td><button type="button" class="btn btn-sm btn-outline-danger removeRow" title="মুছুন"><i class="bi bi-trash"></i></button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-sm btn-outline-primary" id="addRow"><i class="bi bi-plus-lg"></i> আরও প্রোডাক্ট যোগ</button>
      <div class="form-text">স্টক লাল হলে বিক্রয় হবে না — আগে স্টক বাড়ান</div>
    </div>

    <!-- Step 3: Totals -->
    <div class="card" style="background:#f8fafc;border:1px dashed var(--border)">
      <div class="card-body">
        <h6 class="fw-bold" style="color:var(--primary)"><span style="background:var(--primary);color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px">3</span> হিসাব</h6>
        <div class="row g-3 align-items-end">
          <div class="col-md-3"><label class="form-label">ছাড় (Discount)</label><input name="discount" type="number" step="0.01" min="0" class="form-control" value="<?= e($_POST['discount']??'0') ?>" id="discount"></div>
          <div class="col-md-3"><label class="form-label">জমা (Paid)</label><input name="paid" type="number" step="0.01" min="0" class="form-control" value="<?= e($_POST['paid']??'0') ?>" id="paid"></div>
          <div class="col-md-6">
            <div class="bg-white border rounded-3 p-3">
              <div class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><strong id="subtotal">0.00</strong></div>
              <div class="d-flex justify-content-between"><span class="text-muted">মোট (Total)</span><strong id="total" style="font-size:18px;color:var(--primary)">0.00</strong></div>
              <div class="d-flex justify-content-between"><span>বাকি (Due)</span><strong id="due" class="text-danger">0.00</strong></div>
              <div class="small text-muted mt-1">Due = Total − Paid — স্বয়ংক্রিয়</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex gap-2">
    <button name="create_sale" value="1" class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> বিক্রয় সেভ করুন</button>
    <a href="index.php?page=sales" class="btn btn-ghost">বাতিল</a>
    <span class="ms-auto small text-muted align-self-center">স্টক ও লেজার স্বয়ংক্রিয় আপডেট হবে</span>
  </div>
</form>

<script>
(function(){
  const table=document.getElementById('itemsTable').querySelector('tbody');
  const discEl=document.getElementById('discount');
  const paidEl=document.getElementById('paid');
  function recalc(){
    let sub=0;
    table.querySelectorAll('tr').forEach(tr=>{
      const q=parseFloat(tr.querySelector('.qty')?.value||0);
      const p=parseFloat(tr.querySelector('.price')?.value||0);
      const line=isNaN(q)||isNaN(p)?0:q*p;
      tr.querySelector('.lineTotal').textContent=line.toFixed(2);
      sub+=line;
    });
    const disc=parseFloat(discEl.value||0)||0;
    const paid=parseFloat(paidEl.value||0)||0;
    const total=Math.max(0, sub - disc);
    const due=Math.max(0, total - paid);
    document.getElementById('subtotal').textContent=sub.toFixed(2);
    document.getElementById('total').textContent=total.toFixed(2);
    document.getElementById('due').textContent=due.toFixed(2);
  }
  table.addEventListener('input', e=>{
    if(e.target.matches('.qty,.price')) recalc();
  });
  table.addEventListener('change', e=>{
    if(e.target.matches('select')){
      const opt=e.target.selectedOptions[0];
      if(opt && opt.dataset.price){
        e.target.closest('tr').querySelector('.price').value=opt.dataset.price;
        e.target.closest('tr').querySelector('.stock').textContent=opt.dataset.stock + ' pcs';
        // highlight low stock
        const stock=parseInt(opt.dataset.stock||0);
        e.target.closest('tr').querySelector('.stock').style.color = stock<5?'#dc2626':stock<10?'#ca8a04':'#16a34a';
        recalc();
      }
    }
  });
  discEl.addEventListener('input', recalc);
  paidEl.addEventListener('input', recalc);
  document.getElementById('addRow').addEventListener('click', ()=>{
    const row=table.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input').forEach(i=>{
      if(i.classList.contains('qty')) i.value=1;
      else if(i.classList.contains('price')) i.value=0;
      else i.value=0;
    });
    row.querySelector('select').selectedIndex=0;
    row.querySelector('.stock').textContent='-';
    row.querySelector('.stock').style.color='';
    row.querySelector('.lineTotal').textContent='0.00';
    table.appendChild(row);
  });
  table.addEventListener('click', e=>{
    if(e.target.closest('.removeRow') && table.querySelectorAll('tr').length>1){
      e.target.closest('tr').remove(); recalc();
    }
  });
  recalc();
})();
</script>
