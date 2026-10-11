<?php
$pdo=Database::pdo();
$svc=new PccService($pdo);
$accounts=$pdo->query("SELECT * FROM accounts WHERE is_active=1 ORDER BY id")->fetchAll();
$districts=["Dhaka","Chattogram","Rajshahi","Khulna","Barishal","Sylhet","Rangpur","Mymensingh","Cumilla","Gazipur","Narayanganj","Sylhet","Bogura","Jessore","Dinajpur","Faridpur","Tangail","Cox's Bazar","Pabna","Kushtia","Noakhali","Feni","Manikganj"];
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $error="CSRF invalid";
  else {
    try{
      $data=[
        'applicant_name'=>trim($_POST['applicant_name']??''),
        'applicant_name_bn'=>trim($_POST['applicant_name_bn']??''),
        'father_name'=>trim($_POST['father_name']??''),
        'mother_name'=>trim($_POST['mother_name']??''),
        'dob'=>$_POST['dob']??null,
        'nid_no'=>trim($_POST['nid_no']??''),
        'passport_no'=>trim($_POST['passport_no']??''),
        'passport_issue_date'=>$_POST['passport_issue_date']??null,
        'mobile'=>trim($_POST['mobile']??''),
        'email'=>trim($_POST['email']??''),
        'present_address'=>trim($_POST['present_address']??''),
        'permanent_address'=>trim($_POST['permanent_address']??''),
        'thana'=>trim($_POST['thana']??''),
        'district'=>trim($_POST['district']??''),
        'purpose'=>$_POST['purpose']??null,
        'reference_person'=>trim($_POST['reference_person']??''),
        'reference_phone'=>trim($_POST['reference_phone']??''),
        'govt_fee'=>$_POST['govt_fee']??0,
        'service_charge'=>$_POST['service_charge']??0,
        'other_fee'=>$_POST['other_fee']??0,
        'paid_amount'=>$_POST['paid_amount']??0,
        'payment_method'=>$_POST['payment_method']??'cash',
        'transaction_ref'=>trim($_POST['transaction_ref']??''),
        'received_date'=>$_POST['received_date']??date('Y-m-d'),
        'expected_delivery_date'=>$_POST['expected_delivery_date']??null,
        'notes'=>trim($_POST['notes']??''),
        'status'=>'Receive',
        'account_id'=>$_POST['account_id']??null,
      ];
      // server validation
      if(!preg_match('/^01[3-9]\d{8}$/',$data['mobile'])) throw new Exception("Mobile must be 11 digits starting 01");
      $id=$svc->create($data, $_FILES['attachment']??null);
      $row=$svc->get($id);
      flash('success',"Case created! Tracking: ".$row['tracking_id']);
      header("Location: index.php?page=pcc_view&id=$id");
      exit;
    } catch(Exception $e){ $error=$e->getMessage(); }
  }
  // preserve old for repop
  $_SESSION['_old']=$_POST;
}
$defGovt=setting('default_govt_fee','500');
$defService=setting('default_service_charge','1500');
?>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="card">
  <?= Security::csrfField() ?>
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-plus-circle"></i> নতুন PCC কেস রিসিভ / Receive New PCC Case</span>
    <span class="small text-muted">Tracking auto-generate হবে</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <h6 class="text-primary">Applicant Information</h6>
        <div class="mb-2"><label class="form-label">Applicant Name (English) *</label><input name="applicant_name" class="form-control" required value="<?= e($_POST['applicant_name']??'') ?>"></div>
        <div class="mb-2"><label class="form-label">নাম (বাংলা)</label><input name="applicant_name_bn" class="form-control" value="<?= e($_POST['applicant_name_bn']??'') ?>" placeholder="মোঃ রহিম"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Father Name</label><input name="father_name" class="form-control" value="<?= e($_POST['father_name']??'') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Mother Name</label><input name="mother_name" class="form-control" value="<?= e($_POST['mother_name']??'') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= e($_POST['dob']??'') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">NID No</label><input name="nid_no" class="form-control" value="<?= e($_POST['nid_no']??'') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Passport No</label><input name="passport_no" class="form-control" value="<?= e($_POST['passport_no']??'') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Passport Issue Date</label><input type="date" name="passport_issue_date" class="form-control" value="<?= e($_POST['passport_issue_date']??'') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Mobile *</label><input name="mobile" class="form-control" required pattern="01[3-9][0-9]{8}" placeholder="017XXXXXXXX" value="<?= e($_POST['mobile']??'') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?= e($_POST['email']??'') ?>"></div>
        </div>
      </div>
      <div class="col-md-6">
        <h6 class="text-primary">Address & Reference</h6>
        <div class="mb-2"><label class="form-label">Present Address</label><textarea name="present_address" class="form-control" rows="2"><?= e($_POST['present_address']??'') ?></textarea></div>
        <div class="mb-2"><label class="form-label">Permanent Address</label><textarea name="permanent_address" class="form-control" rows="2"><?= e($_POST['permanent_address']??'') ?></textarea></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Thana</label><input name="thana" class="form-control" value="<?= e($_POST['thana']??'') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">District</label><select name="district" class="form-select"><option value="">Select</option><?php foreach($districts as $d): ?><option <?= (($_POST['district']??'')==$d?'selected':'') ?>><?= e($d) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-2"><label class="form-label">Purpose</label><select name="purpose" class="form-select"><option value="">Select</option><option <?= (($_POST['purpose']??'')=='Job'?'selected':'') ?>>Job</option><option <?= (($_POST['purpose']??'')=='Visa'?'selected':'') ?>>Visa</option><option <?= (($_POST['purpose']??'')=='Other'?'selected':'') ?>>Other</option></select></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Reference Person</label><input name="reference_person" class="form-control" value="<?= e($_POST['reference_person']??'') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Reference Phone</label><input name="reference_phone" class="form-control" value="<?= e($_POST['reference_phone']??'') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Attachment (PDF/JPG/PNG, max 5MB)</label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
      </div>
    </div>
    <hr>
    <h6 class="text-primary">Payment & Dates</h6>
    <div class="row g-2">
      <div class="col-6 col-md-2"><label class="form-label">Govt Fee</label><input name="govt_fee" type="number" step="0.01" class="form-control" value="<?= e($_POST['govt_fee']??$defGovt) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label">Service Charge</label><input name="service_charge" type="number" step="0.01" class="form-control" value="<?= e($_POST['service_charge']??$defService) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label">Other Fee</label><input name="other_fee" type="number" step="0.01" class="form-control" value="<?= e($_POST['other_fee']??'0') ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label">Paid Amount</label><input name="paid_amount" type="number" step="0.01" class="form-control" value="<?= e($_POST['paid_amount']??'0') ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="bank">Bank</option><option value="nagad">Nagad</option></select></div>
      <div class="col-6 col-md-2"><label class="form-label">Account</label><select name="account_id" class="form-select"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Transaction Ref</label><input name="transaction_ref" class="form-control" placeholder="TRX..." value="<?= e($_POST['transaction_ref']??'') ?>"></div>
      <div class="col-md-3"><label class="form-label">Received Date *</label><input type="date" name="received_date" class="form-control" required value="<?= e($_POST['received_date']??date('Y-m-d')) ?>"></div>
      <div class="col-md-3"><label class="form-label">Expected Delivery</label><input type="date" name="expected_delivery_date" class="form-control" value="<?= e($_POST['expected_delivery_date']??'') ?>"></div>
      <div class="col-md-3"><label class="form-label">Notes</label><input name="notes" class="form-control" value="<?= e($_POST['notes']??'') ?>"></div>
    </div>
    <div class="small text-muted mt-2">Due auto-calculated: Total - Paid. Govt fee + Other are direct costs, not revenue.</div>
  </div>
  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Case</button>
    <a href="index.php?page=pcc_list" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
