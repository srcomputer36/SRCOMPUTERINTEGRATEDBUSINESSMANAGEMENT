<?php
$pdo=Database::pdo();
$svc=new PccService($pdo);
$id=(int)($_GET['id']??0);
$case=$svc->get($id);
if(!$case){ echo "<div class='alert alert-danger'>Case not found</div>"; return; }
$hist=$svc->statusHistory($id);
$payments=$svc->payments($id);
$files=$pdo->prepare("SELECT * FROM case_files WHERE case_id=? ORDER BY uploaded_at DESC");
$files->execute([$id]); $files=$files->fetchAll();
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1")->fetchAll();
// handle status update
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF invalid');
  else {
    try{ $svc->updateStatus($id, $_POST['new_status'], $_POST['status_notes']??''); flash('success','Status updated'); header("Location: index.php?page=pcc_view&id=$id"); exit; }catch(Exception $e){ flash('error',$e->getMessage()); }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_payment'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF invalid');
  else {
    try{
      $amt=(float)($_POST['amount']??0);
      $svc->addPayment($id,$amt,$_POST['payment_date']??date('Y-m-d'),$_POST['payment_method']??'cash', trim($_POST['transaction_ref']??'')?:null, $_POST['account_id']?:null);
      flash('success','Payment added');
      header("Location: index.php?page=pcc_view&id=$id");
      exit;
    }catch(Exception $e){ flash('error',$e->getMessage()); }
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_delivery'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF invalid');
  else {
    $pdo->prepare("UPDATE pcc_cases SET delivery_recipient=?, delivery_phone=?, delivered_date=?, status='Delivered', updated_at=NOW() WHERE id=?")->execute([$_POST['delivery_recipient'],$_POST['delivery_phone'],$_POST['delivered_date']??date('Y-m-d'),$id]);
    $pdo->prepare("INSERT INTO case_status_history (case_id,from_status,to_status,changed_by,notes) VALUES (?,?,?,?,?)")->execute([$id,$case['status'],'Delivered',$_SESSION['user_id']??null,'Delivered to '.$_POST['delivery_recipient']]);
    flash('success','Delivery recorded');
    header("Location: index.php?page=pcc_view&id=$id");
    exit;
  }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_file']) && !empty($_FILES['file']['tmp_name'])){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) flash('error','CSRF invalid');
  else {
    try{
      $ref=new PccService($pdo);
      // use private saveFile via reflection? simpler inline
      $file=$_FILES['file'];
      $allowed=cfg('upload.allowed_mimes'); $max=cfg('upload.max_size');
      if($file['size']>$max) throw new Exception("Too large");
      $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$file['tmp_name']); finfo_close($finfo);
      if($allowed && !in_array($mime,$allowed)) throw new Exception("Invalid type $mime");
      $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
      $fname=$id.'_'.time().'_'.preg_replace('/[^a-z0-9]/i','_',$file['name']);
      $dir=cfg('upload.dir'); $caseDir=$dir.'/pcc/'.$id; if(!is_dir($caseDir)) mkdir($caseDir,0755,true);
      $dest=$caseDir.'/'.$fname; move_uploaded_file($file['tmp_name'],$dest);
      $pdo->prepare("INSERT INTO case_files (case_id,file_name,file_path,drive_link,mime_type,size,uploaded_by) VALUES (?,?,?,?,?,?,?)")->execute([$id,$file['name'],$dest,'storage/uploads/pcc/'.$id.'/'.$fname,$mime,$file['size'],$_SESSION['user_id']??null]);
      flash('success','File uploaded'); header("Location: index.php?page=pcc_view&id=$id"); exit;
    }catch(Exception $e){ flash('error',$e->getMessage()); }
  }
}
$case=$svc->get($id); // refresh
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="index.php?page=pcc_list" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
  <a href="index.php?page=pcc_edit&id=<?= (int)$case['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Edit</a>
  <button onclick="window.print()" class="btn btn-sm btn-outline-dark no-print"><i class="bi bi-printer"></i> Print</button>
  <span class="ms-auto badge badge-status status-<?= e($case['status']) ?> fs-6"><?= e($case['status']) ?></span>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between"><span>Tracking: <strong><?= e($case['tracking_id']) ?></strong></span><span class="small text-muted">Received <?= bd_date($case['received_date']) ?></span></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="text-primary">Applicant</h6>
            <table class="table table-sm">
              <tr><th>Name</th><td><?= e($case['applicant_name']) ?></td></tr>
              <tr><th>বাংলা নাম</th><td><?= e($case['applicant_name_bn']?:'—') ?></td></tr>
              <tr><th>Father</th><td><?= e($case['father_name']?:'—') ?></td></tr>
              <tr><th>Mother</th><td><?= e($case['mother_name']?:'—') ?></td></tr>
              <tr><th>DOB</th><td><?= bd_date($case['dob']) ?></td></tr>
              <tr><th>NID</th><td><?= e($case['nid_no']?:'—') ?></td></tr>
              <tr><th>Passport</th><td><?= e($case['passport_no']?:'—') ?> <?php if($case['passport_issue_date']): ?><small class="text-muted">Issue <?= bd_date($case['passport_issue_date']) ?></small><?php endif; ?></td></tr>
              <tr><th>Mobile</th><td><?= e($case['mobile']) ?></td></tr>
              <tr><th>Email</th><td><?= e($case['email']?:'—') ?></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="text-primary">Address & Payment</h6>
            <table class="table table-sm">
              <tr><th>Present</th><td><?= e($case['present_address']?:'—') ?></td></tr>
              <tr><th>Permanent</th><td><?= e($case['permanent_address']?:'—') ?></td></tr>
              <tr><th>Thana/District</th><td><?= e($case['thana']?:'—') ?> / <?= e($case['district']?:'—') ?></td></tr>
              <tr><th>Purpose</th><td><?= e($case['purpose']?:'—') ?></td></tr>
              <tr><th>Reference</th><td><?= e($case['reference_person']?:'—') ?> <?= $case['reference_phone']?'('.e($case['reference_phone']).')':'' ?></td></tr>
              <tr><th>Govt Fee</th><td><?= bdt($case['govt_fee']) ?></td></tr>
              <tr><th>Service Charge</th><td><?= bdt($case['service_charge']) ?></td></tr>
              <tr><th>Other</th><td><?= bdt($case['other_fee']) ?></td></tr>
              <tr><th>Total</th><td><strong><?= bdt($case['total_fee']) ?></strong></td></tr>
              <tr><th>Paid</th><td class="text-success fw-bold"><?= bdt($case['paid_amount']) ?></td></tr>
              <tr><th>Due</th><td class="text-danger fw-bold"><?= bdt($case['due_amount']) ?></td></tr>
            </table>
          </div>
        </div>
        <?php if($case['notes']): ?><div class="alert alert-light border small">Notes: <?= e($case['notes']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Payment History</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>#</th><th>Date</th><th>Amount</th><th>Method</th><th>Ref</th></tr></thead>
          <tbody>
            <?php foreach($payments as $i=>$p): ?><tr><td><?= $i+1 ?></td><td><?= bd_date($p['payment_date']) ?></td><td><?= bdt($p['amount']) ?></td><td><?= e($p['payment_method']) ?></td><td><?= e($p['transaction_ref']?:'—') ?></td></tr><?php endforeach; if(!$payments): ?><tr><td colspan="5" class="text-center text-muted">No payments</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if((float)$case['due_amount']>0.001): ?>
      <div class="card-footer">
        <form method="POST" class="row g-2 align-items-end">
          <?= Security::csrfField() ?>
          <div class="col-3"><label class="form-label small">Amount</label><input name="amount" type="number" step="0.01" max="<?= (float)$case['due_amount'] ?>" class="form-control" required placeholder="<?= $case['due_amount'] ?>"></div>
          <div class="col-3"><label class="form-label small">Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="col-2"><label class="form-label small">Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="bank">Bank</option><option value="nagad">Nagad</option></select></div>
          <div class="col-2"><label class="form-label small">Account</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-2"><label class="form-label small">Ref</label><input name="transaction_ref" class="form-control" placeholder="TRX"></div>
          <div class="col-12"><button name="add_payment" value="1" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Add Payment</button> <small class="text-muted">Due <?= bdt($case['due_amount']) ?></small></div>
        </form>
      </div>
      <?php else: ?><div class="card-footer text-success small"><i class="bi bi-check-circle"></i> Fully Paid</div><?php endif; ?>
    </div>

    <div class="card mb-3">
      <div class="card-header">Files</div>
      <div class="card-body">
        <?php foreach($files as $f): ?><div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2"><span><i class="bi bi-file-earmark"></i> <?= e($f['file_name']) ?> <small class="text-muted"><?= round(($f['size']??0)/1024,1) ?> KB</small></span><a href="<?= e($f['drive_link']?:$f['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open</a></div><?php endforeach; if(!$files): ?><div class="text-muted small">No files</div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="mt-3 d-flex gap-2">
          <?= Security::csrfField() ?><input type="file" name="file" class="form-control" required><button name="upload_file" value="1" class="btn btn-outline-secondary">Upload</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">Status Update</div>
      <div class="card-body">
        <form method="POST">
          <?= Security::csrfField() ?>
          <div class="mb-2"><label class="form-label small">Current</label><div><span class="badge badge-status status-<?= e($case['status']) ?>"><?= e($case['status']) ?></span></div></div>
          <div class="mb-2"><label class="form-label small">New Status</label><select name="new_status" class="form-select" required><option value="">Select</option><?php foreach(['Receive','SS Printed','Sign by SP/DC','MOFA Verification','Ready for Delivery','Delivered'] as $s): ?><option <?= $s===$case['status']?'disabled':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
          <div class="mb-2"><label class="form-label small">Notes</label><input name="status_notes" class="form-control"></div>
          <button name="update_status" value="1" class="btn btn-primary w-100">Update Status</button>
        </form>
        <hr>
        <h6 class="small">History</h6>
        <div class="small">
          <?php foreach($hist as $h): ?><div class="border-start ps-2 ms-1 mb-2" style="border-color:#3b82f6 !important"><div><?= e($h['from_status']??'—') ?> → <strong><?= e($h['to_status']) ?></strong></div><div class="text-muted"><?= bd_datetime($h['changed_at']) ?> • <?= e($h['username']??'system') ?><?php if($h['notes']): ?> • <?= e($h['notes']) ?><?php endif; ?></div></div><?php endforeach; if(!$hist): ?><div class="text-muted">No history</div><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header">Delivery</div>
      <div class="card-body">
        <?php if($case['status']==='Delivered'): ?>
          <div class="alert alert-success small">Delivered to <?= e($case['delivery_recipient']) ?> (<?= e($case['delivery_phone']) ?>) on <?= bd_date($case['delivered_date']) ?></div>
        <?php else: ?>
          <form method="POST">
            <?= Security::csrfField() ?>
            <div class="mb-2"><label class="form-label small">Recipient Name</label><input name="delivery_recipient" class="form-control" required value="<?= e($case['delivery_recipient']??$case['applicant_name']) ?>"></div>
            <div class="mb-2"><label class="form-label small">Recipient Phone</label><input name="delivery_phone" class="form-control" required value="<?= e($case['delivery_phone']??$case['mobile']) ?>"></div>
            <div class="mb-2"><label class="form-label small">Delivered Date</label><input type="date" name="delivered_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
            <button name="add_delivery" value="1" class="btn btn-success w-100" data-confirm="Mark as Delivered?"><i class="bi bi-box-seam"></i> Mark Delivered</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
