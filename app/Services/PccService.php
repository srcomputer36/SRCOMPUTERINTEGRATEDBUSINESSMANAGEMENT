<?php
require_once __DIR__ . '/AccountingService.php';
class PccService {
    private PDO $pdo;
    private AccountingService $acct;
    public function __construct(PDO $pdo){ $this->pdo=$pdo; $this->acct=new AccountingService($pdo); }

    public function create(array $data, ?array $fileInfo=null): int {
        // validation
        $required = ['applicant_name','mobile','received_date'];
        foreach($required as $r) if(empty($data[$r])) throw new Exception("Field $r required");
        // sanitize
        $govt = (float)($data['govt_fee']??0);
        $service = (float)($data['service_charge']??0);
        $other = (float)($data['other_fee']??0);
        $feeMode = setting('fee_mode','service_only');
        if ($feeMode === 'service_only') $total = $service; // per DATABASE_MAPPING §8 Option A
        else $total = $govt + $service + $other;
        // If user explicitly provided total_fee, use it? but enforce logic
        if (isset($data['total_fee']) && is_numeric($data['total_fee'])) $total = (float)$data['total_fee'];

        $paid = (float)($data['paid_amount']??0);
        if ($paid > $total) throw new Exception("Paid cannot exceed total");
        $due = $total - $paid;

        // duplicate warning? check passport active
        if (!empty($data['passport_no'])) {
            $chk=$this->pdo->prepare("SELECT tracking_id FROM pcc_cases WHERE passport_no=? AND status!='Delivered' AND deleted_at IS NULL LIMIT 1");
            $chk->execute([$data['passport_no']]);
            if($row=$chk->fetch()) { /* warning not block — store note? */ }
        }

        $tracking = generate_tracking_id($this->pdo);
        $customerId = $this->findOrCreateCustomer($data);

        $this->pdo->beginTransaction();
        try {
            $sql="INSERT INTO pcc_cases (tracking_id, applicant_name, applicant_name_bn, father_name, mother_name, dob, nid_no, passport_no, passport_issue_date, mobile, email, present_address, permanent_address, thana, district, purpose, reference_person, reference_phone, customer_id, govt_fee, service_charge, other_fee, total_fee, paid_amount, due_amount, payment_method, transaction_ref, status, received_date, expected_delivery_date, notes, file_drive_id, file_drive_link, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt=$this->pdo->prepare($sql);
            $stmt->execute([
                $tracking,
                $data['applicant_name'],
                $data['applicant_name_bn']??null,
                $data['father_name']??null,
                $data['mother_name']??null,
                $data['dob']?:null,
                $data['nid_no']??null,
                $data['passport_no']??null,
                $data['passport_issue_date']?:null,
                $data['mobile'],
                $data['email']??null,
                $data['present_address']??null,
                $data['permanent_address']??null,
                $data['thana']??null,
                $data['district']??null,
                $data['purpose']??null,
                $data['reference_person']??null,
                $data['reference_phone']??null,
                $customerId,
                $govt, $service, $other, $total, $paid, $due,
                $data['payment_method']??null,
                $data['transaction_ref']?:null,
                $data['status']??'Receive',
                $data['received_date'],
                $data['expected_delivery_date']?:null,
                $data['notes']??null,
                $data['file_drive_id']??null,
                $data['file_drive_link']??null,
                $_SESSION['user_id']??null
            ]);
            $caseId=(int)$this->pdo->lastInsertId();
            // status history
            $this->pdo->prepare("INSERT INTO case_status_history (case_id, from_status, to_status, changed_by, notes) VALUES (?,?,?, ?, ?)")->execute([$caseId,null,$data['status']??'Receive', $_SESSION['user_id']??null, 'Case created']);
            // payments record if paid >0
            if ($paid>0){
                $this->pdo->prepare("INSERT INTO payments (type, case_id, customer_id, amount, payment_date, payment_method, transaction_ref, created_by) VALUES ('pcc', ?, ?, ?, ?, ?, ?, ?)")->execute([$caseId,$customerId,$paid,$data['received_date'],$data['payment_method']??'cash',$data['transaction_ref']?:null, $_SESSION['user_id']??null]);
                // accounting
                $this->acct->postPccPayment($caseId, $paid, [
                    'transaction_date'=>$data['received_date'],
                    'account_id'=>$data['account_id']??null,
                    'payment_method'=>$data['payment_method']??null,
                    'reference'=>$data['transaction_ref']??null,
                    'description'=>"PCC Payment $tracking",
                    'customer_id'=>$customerId
                ]);
            }
            // file if uploaded
            if ($fileInfo && !empty($fileInfo['tmp_name'])){
                $this->saveFile($caseId,$fileInfo);
            }
            log_activity($this->pdo,'create','pcc',$caseId,"Created PCC $tracking");
            $this->pdo->commit();
            return $caseId;
        } catch(Exception $e){
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            // if duplicate tracking, retry once
            if (strpos($e->getMessage(),'uq_tracking')!==false){
                // regenerate and retry once
                $data['_retry']=1;
                return $this->create($data,$fileInfo);
            }
            throw $e;
        }
    }
    public function update(int $id, array $data): void {
        $old=$this->get($id);
        if(!$old) throw new Exception("Case not found");
        // compute total/due again
        $govt = isset($data['govt_fee'])? (float)$data['govt_fee'] : (float)$old['govt_fee'];
        $service = isset($data['service_charge'])? (float)$data['service_charge'] : (float)$old['service_charge'];
        $other = isset($data['other_fee'])? (float)$data['other_fee'] : (float)$old['other_fee'];
        $feeMode=setting('fee_mode','service_only');
        if (isset($data['total_fee']) && is_numeric($data['total_fee'])) $total=(float)$data['total_fee'];
        else $total = ($feeMode==='service_only')? $service : $govt+$service+$other;
        // paid_amount maybe updated via payment flow; but allow edit if provided
        $paid = isset($data['paid_amount'])? (float)$data['paid_amount'] : (float)$old['paid_amount'];
        $due = $total - $paid;
        $this->pdo->beginTransaction();
        try{
            $fields=['applicant_name','applicant_name_bn','father_name','mother_name','dob','nid_no','passport_no','passport_issue_date','mobile','email','present_address','permanent_address','thana','district','purpose','reference_person','reference_phone','govt_fee','service_charge','other_fee','total_fee','paid_amount','due_amount','payment_method','transaction_ref','expected_delivery_date','delivery_recipient','delivery_phone','notes'];
            $sets=[]; $vals=[];
            foreach($fields as $f){
                if(array_key_exists($f,$data)){
                    $sets[]="`$f`=?";
                    // handle numeric
                    if(in_array($f,['govt_fee','service_charge','other_fee'])) $vals[]=(float)$data[$f];
                    else $vals[]=$data[$f]?:null;
                }
            }
            // enforce total/due
            $sets[]="total_fee=?"; $vals[]=$total;
            $sets[]="paid_amount=?"; $vals[]=$paid;
            $sets[]="due_amount=?"; $vals[]=$due;
            // status? only via updateStatus to ensure history, but allow if provided
            if(isset($data['status']) && $data['status']!==$old['status']){
                // will add history after
                $sets[]="status=?"; $vals[]=$data['status'];
                if($data['status']==='Delivered' && empty($data['delivered_date'])) {
                    $sets[]="delivered_date=?"; $vals[]=date('Y-m-d');
                }
            }
            if(empty($sets)) { $this->pdo->commit(); return; }
            $vals[]=$id;
            $sql="UPDATE pcc_cases SET ".implode(',',$sets).", updated_at=NOW() WHERE id=?";
            $this->pdo->prepare($sql)->execute($vals);
            if(isset($data['status']) && $data['status']!==$old['status']){
                $this->pdo->prepare("INSERT INTO case_status_history (case_id, from_status, to_status, changed_by, notes) VALUES (?,?,?,?,?)")->execute([$id,$old['status'],$data['status'],$_SESSION['user_id']??null,'Status updated via edit']);
            }
            log_activity($this->pdo,'update','pcc',$id,"Updated PCC ".$old['tracking_id']);
            $this->pdo->commit();
        } catch(Exception $e){ if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function updateStatus(int $id, string $newStatus, string $notes=''): void {
        $allowed=['Receive','SS Printed','Sign by SP/DC','MOFA Verification','Ready for Delivery','Delivered'];
        if(!in_array($newStatus,$allowed)) throw new Exception("Invalid status");
        $c=$this->get($id);
        if(!$c) throw new Exception("Case not found");
        $strict = setting('status_strict','0')==='1';
        if($strict){
            $order=array_flip($allowed);
            $curIdx=$order[$c['status']] ?? 0;
            $newIdx=$order[$newStatus] ?? 0;
            if($newIdx < $curIdx) throw new Exception("Cannot revert status when strict mode enabled");
            if($newIdx > $curIdx+1) throw new Exception("Cannot skip status steps in strict mode");
        }
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("UPDATE pcc_cases SET status=?, delivered_date=?, updated_at=NOW() WHERE id=?")->execute([$newStatus, $newStatus==='Delivered'?date('Y-m-d'): $c['delivered_date'], $id]);
            $this->pdo->prepare("INSERT INTO case_status_history (case_id, from_status, to_status, changed_by, notes) VALUES (?,?,?,?,?)")->execute([$id,$c['status'],$newStatus,$_SESSION['user_id']??null,$notes]);
            log_activity($this->pdo,'status','pcc',$id,"Status {$c['status']} → $newStatus");
            $this->pdo->commit();
        } catch(Exception $e){ if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function bulkUpdateStatus(array $ids, string $newStatus): array {
        $result=['success'=>0,'failed'=>[]];
        foreach($ids as $id){
            try{ $this->updateStatus((int)$id,$newStatus,'Bulk update'); $result['success']++; }
            catch(Exception $e){ $result['failed'][]=['id'=>$id,'error'=>$e->getMessage()]; }
        }
        return $result;
    }
    public function addPayment(int $caseId, float $amount, string $date, string $method='cash', ?string $ref=null, ?int $account_id=null): void {
        $c=$this->get($caseId);
        if(!$c) throw new Exception("Case not found");
        if($amount<=0) throw new Exception("Amount must be positive");
        $newPaid=(float)$c['paid_amount'] + $amount;
        if($newPaid > (float)$c['total_fee']+0.001) throw new Exception("Paid exceeds total");
        // idempotency: check duplicate ref
        if($ref){
            $chk=$this->pdo->prepare("SELECT id FROM payments WHERE transaction_ref=? LIMIT 1");
            $chk->execute([$ref]);
            if($chk->fetchColumn()) throw new Exception("Duplicate transaction reference");
        }
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("INSERT INTO payments (type, case_id, customer_id, amount, payment_date, payment_method, transaction_ref, account_id, created_by) VALUES ('pcc',?,?,?,?,?,?,?,?)")->execute([$caseId,$c['customer_id'],$amount,$date,$method,$ref,$account_id,$_SESSION['user_id']??null]);
            $this->pdo->prepare("UPDATE pcc_cases SET paid_amount=?, due_amount=total_fee-?, updated_at=NOW() WHERE id=?")->execute([$newPaid,$newPaid,$caseId]);
            $this->acct->postPccPayment($caseId,$amount,['transaction_date'=>$date,'account_id'=>$account_id,'payment_method'=>$method,'reference'=>$ref,'description'=>'PCC Payment '.$c['tracking_id'],'customer_id'=>$c['customer_id']]);
            log_activity($this->pdo,'payment','pcc',$caseId,"Payment $amount for ".$c['tracking_id']);
            $this->pdo->commit();
        } catch(Exception $e){ if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function get(int $id): ?array {
        $stmt=$this->pdo->prepare("SELECT * FROM pcc_cases WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function list(array $filters=[], int $page=1, int $per=20): array {
        $where=[]; $vals=[];
        if(!empty($filters['search'])){
            $s='%'.$filters['search'].'%';
            $where[]="(applicant_name LIKE ? OR applicant_name_bn LIKE ? OR tracking_id LIKE ? OR passport_no LIKE ? OR mobile LIKE ? OR district LIKE ?)";
            $vals=array_merge($vals,[$s,$s,$s,$s,$s,$s]);
        }
        if(!empty($filters['status'])){ $where[]="status=?"; $vals[]=$filters['status']; }
        if(!empty($filters['district'])){ $where[]="district=?"; $vals[]=$filters['district']; }
        if(!empty($filters['from'])){ $where[]="received_date >= ?"; $vals[]=$filters['from']; }
        if(!empty($filters['to'])){ $where[]="received_date <= ?"; $vals[]=$filters['to']; }
        $where[]="deleted_at IS NULL";
        $w = $where? "WHERE ".implode(" AND ",$where):"";
        $count=$this->pdo->prepare("SELECT COUNT(*) FROM pcc_cases $w");
        $count->execute($vals);
        $total=(int)$count->fetchColumn();
        $pages=max(1, ceil($total/$per));
        $offset=($page-1)*$per;
        $sortCol=in_array($filters['sort']??'', ['received_date','applicant_name','status','total_fee','due_amount','tracking_id']) ? $filters['sort'] : 'received_date';
        $dir = (($filters['dir']??'desc')==='asc')?'ASC':'DESC';
        $stmt=$this->pdo->prepare("SELECT * FROM pcc_cases $w ORDER BY $sortCol $dir, id DESC LIMIT $per OFFSET $offset");
        $stmt->execute($vals);
        $rows=$stmt->fetchAll();
        return ['rows'=>$rows,'total'=>$total,'pages'=>$pages,'page'=>$page];
    }
    public function statusHistory(int $caseId): array {
        $stmt=$this->pdo->prepare("SELECT h.*, u.username FROM case_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE case_id=? ORDER BY changed_at ASC");
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }
    public function payments(int $caseId): array {
        $stmt=$this->pdo->prepare("SELECT * FROM payments WHERE case_id=? ORDER BY payment_date ASC, id ASC");
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }
    public function stats(): array {
        $total=$this->pdo->query("SELECT COUNT(*) FROM pcc_cases WHERE deleted_at IS NULL")->fetchColumn();
        $today=$this->pdo->query("SELECT COUNT(*) FROM pcc_cases WHERE received_date=CURDATE() AND deleted_at IS NULL")->fetchColumn();
        $dueSum=$this->pdo->query("SELECT COALESCE(SUM(due_amount),0) FROM pcc_cases WHERE deleted_at IS NULL")->fetchColumn();
        $paidSum=$this->pdo->query("SELECT COALESCE(SUM(paid_amount),0) FROM pcc_cases WHERE deleted_at IS NULL")->fetchColumn();
        $byStatus=$this->pdo->query("SELECT status, COUNT(*) c FROM pcc_cases WHERE deleted_at IS NULL GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
        $monthly=$this->pdo->query("SELECT DATE_FORMAT(received_date,'%Y-%m') m, COUNT(*) c FROM pcc_cases WHERE deleted_at IS NULL GROUP BY m ORDER BY m DESC LIMIT 6")->fetchAll();
        return compact('total','today','dueSum','paidSum','byStatus','monthly');
    }
    private function findOrCreateCustomer(array $data): ?int {
        $phone = $data['mobile'] ?? $data['reference_phone'] ?? null;
        $name = $data['applicant_name'] ?? $data['reference_person'] ?? null;
        if(!$phone || !$name) return null;
        $stmt=$this->pdo->prepare("SELECT id FROM customers WHERE phone=? LIMIT 1");
        $stmt->execute([$phone]);
        $id=$stmt->fetchColumn();
        if($id) return (int)$id;
        try{
            $this->pdo->prepare("INSERT INTO customers (name, phone, address, created_by) VALUES (?,?,?,?)")->execute([$name,$phone,$data['present_address']??null,$_SESSION['user_id']??null]);
            return (int)$this->pdo->lastInsertId();
        } catch(Exception $e){ return null; }
    }
    private function saveFile(int $caseId, array $file): void {
        $cfg=cfg();
        $allowed=$cfg['upload']['allowed_mimes']??[];
        $max=$cfg['upload']['max_size']??5242880;
        if($file['error']!==UPLOAD_ERR_OK) throw new Exception("Upload error");
        if($file['size']>$max) throw new Exception("File too large");
        $finfo=finfo_open(FILEINFO_MIME_TYPE);
        $mime=finfo_file($finfo,$file['tmp_name']);
        finfo_close($finfo);
        if($allowed && !in_array($mime,$allowed)) throw new Exception("Invalid file type: $mime");
        $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
        $safe=preg_replace('/[^a-zA-Z0-9]/','_',pathinfo($file['name'],PATHINFO_FILENAME));
        $fname=$caseId.'_'.time().'_'.$safe.'.'.$ext;
        $dir=cfg('upload.dir') ?? dirname(__DIR__,2).'/storage/uploads';
        $caseDir=$dir.'/pcc/'.$caseId;
        if(!is_dir($caseDir)) mkdir($caseDir,0755,true);
        $dest=$caseDir.'/'.$fname;
        if(!move_uploaded_file($file['tmp_name'],$dest)) throw new Exception("Failed to save file");
        // Try Drive upload if mode drive (optional)
        $driveId=null; $driveLink=null;
        if(cfg('google.mode')==='drive' && file_exists(cfg('google.credentials_path'))){
            // Drive API placeholder — not required for local fallback
            // Could integrate google/apiclient here
        } else {
            $driveLink='storage/uploads/pcc/'.$caseId.'/'.$fname; // relative for protected download via PHP
        }
        $this->pdo->prepare("INSERT INTO case_files (case_id, file_name, file_path, drive_file_id, drive_link, mime_type, size, uploaded_by) VALUES (?,?,?,?,?,?,?,?)")->execute([$caseId,$file['name'],$dest,$driveId,$driveLink,$mime,$file['size'],$_SESSION['user_id']??null]);
        // also update case file_drive_link for quick access
        $this->pdo->prepare("UPDATE pcc_cases SET file_drive_id=?, file_drive_link=? WHERE id=?")->execute([$driveId,$driveLink,$caseId]);
    }
}
