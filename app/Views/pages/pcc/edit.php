<?php
$pdo=Database::pdo();
$svc=new PccService($pdo);
$id=(int)($_GET['id']??0);
$case=$svc->get($id);
if(!$case){ echo "<div class='alert alert-danger'>Not found</div>"; return; }
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Security::verifyCsrf($_POST['_csrf']??'')) $error="CSRF invalid";
  else {
    try{
      $data=[
        'applicant_name'=>trim($_POST['applicant_name']??$case['applicant_name']),
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
        'govt_fee'=>$_POST['govt_fee']??$case['govt_fee'],
        'service_charge'=>$_POST['service_charge']??$case['service_charge'],
        'other_fee'=>$_POST['other_fee']??$case['other_fee'],
        'expected_delivery_date'=>$_POST['expected_delivery_date']??null,
        'notes'=>trim($_POST['notes']??''),
      ];
      // if mobile invalid
      if(!preg_match('/^01[3-9]\d{8}$/',$data['mobile'])) throw new Exception("Invalid mobile");
      $svc->update($id,$data);
      // handle file if new
      if(!empty($_FILES['attachment']['tmp_name'])){
        // reuse upload logic
        $file=$_FILES['attachment'];
        $allowed=cfg('upload.allowed_mimes'); $max=cfg('upload.max_size');
        if($file['size']<=$max){
          $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$file['tmp_name']); finfo_close($finfo);
          if(!$allowed || in_array($mime,$allowed)){
            $fname=$id.'_'.time().'_'.$file['name'];
            $dir=cfg('upload.dir').'/pcc/'.$id; if(!is_dir($dir)) mkdir($dir,0755,true);
            $dest=$dir.'/'.$fname; move_uploaded_file($file['tmp_name'],$dest);
            $pdo->prepare("INSERT INTO case_files (case_id,file_name,file_path,drive_link,mime_type,size,uploaded_by) VALUES (?,?,?,?,?,?,?)")->execute([$id,$file['name'],$dest,'storage/uploads/pcc/'.$id.'/'.$fname,$mime,$file['size'],$_SESSION['user_id']??null]);
          }
        }
      }
      flash('success','Case updated');
      header("Location: index.php?page=pcc_view&id=$id");
      exit;
    }catch(Exception $e){ $error=$e->getMessage(); }
  }
}
?>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="card">
  <?= Security::csrfField() ?>
  <div class="card-header d-flex justify-content-between"><span>Edit Case <?= e($case['tracking_id']) ?></span><a href="index.php?page=pcc_view&id=<?= (int)$case['id'] ?>" class="btn btn-sm btn-outline-secondary">Back</a></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Applicant Name *</label><input name="applicant_name" class="form-control" required value="<?= e($_POST['applicant_name']??$case['applicant_name']) ?>"></div>
      <div class="col-md-6"><label class="form-label">নাম (বাংলা)</label><input name="applicant_name_bn" class="form-control" value="<?= e($_POST['applicant_name_bn']??$case['applicant_name_bn']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Father</label><input name="father_name" class="form-control" value="<?= e($_POST['father_name']??$case['father_name']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Mother</label><input name="mother_name" class="form-control" value="<?= e($_POST['mother_name']??$case['mother_name']) ?>"></div>
      <div class="col-md-3"><label class="form-label">DOB</label><input type="date" name="dob" class="form-control" value="<?= e($_POST['dob']??$case['dob']) ?>"></div>
      <div class="col-md-3"><label class="form-label">NID</label><input name="nid_no" class="form-control" value="<?= e($_POST['nid_no']??$case['nid_no']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Passport</label><input name="passport_no" class="form-control" value="<?= e($_POST['passport_no']??$case['passport_no']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Issue Date</label><input type="date" name="passport_issue_date" class="form-control" value="<?= e($_POST['passport_issue_date']??$case['passport_issue_date']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Mobile *</label><input name="mobile" class="form-control" required value="<?= e($_POST['mobile']??$case['mobile']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Email</label><input name="email" class="form-control" value="<?= e($_POST['email']??$case['email']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Thana</label><input name="thana" class="form-control" value="<?= e($_POST['thana']??$case['thana']) ?>"></div>
      <div class="col-md-4"><label class="form-label">District</label><input name="district" class="form-control" value="<?= e($_POST['district']??$case['district']) ?>"></div>
      <div class="col-12"><label class="form-label">Present Address</label><textarea name="present_address" class="form-control"><?= e($_POST['present_address']??$case['present_address']) ?></textarea></div>
      <div class="col-12"><label class="form-label">Permanent Address</label><textarea name="permanent_address" class="form-control"><?= e($_POST['permanent_address']??$case['permanent_address']) ?></textarea></div>
      <div class="col-md-4"><label class="form-label">Purpose</label><select name="purpose" class="form-select"><option <?= ($case['purpose']=='Job'?'selected':'') ?>>Job</option><option <?= ($case['purpose']=='Visa'?'selected':'') ?>>Visa</option><option <?= ($case['purpose']=='Other'?'selected':'') ?>>Other</option></select></div>
      <div class="col-md-4"><label class="form-label">Reference Person</label><input name="reference_person" class="form-control" value="<?= e($_POST['reference_person']??$case['reference_person']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Reference Phone</label><input name="reference_phone" class="form-control" value="<?= e($_POST['reference_phone']??$case['reference_phone']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Govt Fee</label><input name="govt_fee" type="number" step="0.01" class="form-control" value="<?= e($_POST['govt_fee']??$case['govt_fee']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Service Charge</label><input name="service_charge" type="number" step="0.01" class="form-control" value="<?= e($_POST['service_charge']??$case['service_charge']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Other Fee</label><input name="other_fee" type="number" step="0.01" class="form-control" value="<?= e($_POST['other_fee']??$case['other_fee']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Attachment</label><input type="file" name="attachment" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Expected Delivery</label><input type="date" name="expected_delivery_date" class="form-control" value="<?= e($_POST['expected_delivery_date']??$case['expected_delivery_date']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Notes</label><input name="notes" class="form-control" value="<?= e($_POST['notes']??$case['notes']) ?>"></div>
    </div>
  </div>
  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a href="index.php?page=pcc_view&id=<?= (int)$case['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
