<?php
// Import CSV exported from Google Sheets into MySQL
// Usage: php tools/import_sheets.php --file=/path/to.csv --dry-run
// Or via web: upload in Settings? This script is CLI/web hybrid

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Helpers/Functions.php';
require __DIR__ . '/../app/Services/PccService.php';

$options = getopt('', ['file:', 'dry-run']);
$file = $options['file'] ?? null;
$dryRun = isset($options['dry-run']);

if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv']['tmp_name'];
    $dryRun = isset($_POST['dry_run']);
}
if (!$file) {
    if (php_sapi_name() === 'cli') {
        echo "Usage: php tools/import_sheets.php --file=path.csv [--dry-run]\n";
        echo "Or POST via web to this script with file input name 'csv'.\n";
        exit(1);
    } else {
        echo '<form method="POST" enctype="multipart/form-data"><input type="file" name="csv" required><label><input type="checkbox" name="dry_run" checked> Dry run</label><button>Import</button></form>';
        exit;
    }
}
if (!file_exists($file)) { echo "File not found: $file\n"; exit(1); }

$pdo = Database::pdo();
$svc = new PccService($pdo);

$handle = fopen($file, 'r');
if (!$handle) { echo "Cannot open $file\n"; exit(1); }
$header = fgetcsv($handle);
if (!$header) { echo "Empty file\n"; exit(1); }
$header = array_map(fn($h)=>trim($h), $header);
// Map flexible headers to fields
$map = [
    'tracking id' => 'tracking_id', 'tracking' => 'tracking_id', 'id' => 'tracking_id',
    'applicant name' => 'applicant_name', 'name' => 'applicant_name', 'applicant' => 'applicant_name',
    'father' => 'father_name', 'father name' => 'father_name',
    'mother' => 'mother_name', 'mother name' => 'mother_name',
    'dob' => 'dob', 'date of birth' => 'dob',
    'nid' => 'nid_no', 'nid no' => 'nid_no',
    'passport' => 'passport_no', 'passport no' => 'passport_no',
    'mobile' => 'mobile', 'phone' => 'mobile',
    'district' => 'district', 'thana' => 'thana',
    'status' => 'status', 'total' => 'total_fee', 'paid' => 'paid_amount', 'due' => 'due_amount',
    'govt fee' => 'govt_fee', 'service charge' => 'service_charge', 'other fee' => 'other_fee',
    'received date' => 'received_date', 'date' => 'received_date',
];
$colIdx = [];
foreach ($header as $i=>$h) {
    $k = strtolower(trim($h));
    if (isset($map[$k])) $colIdx[$map[$k]] = $i;
    else $colIdx[$k] = $i; // keep raw
}
$total=0; $success=0; $failed=0; $errors=[];
while (($row = fgetcsv($handle)) !== false) {
    $total++;
    if (count($row) < 2) { $failed++; $errors[] = ['row'=>$total,'error'=>'empty row']; continue; }
    // Build data
    $get = fn($field,$def=null)=> isset($colIdx[$field]) && isset($row[$colIdx[$field]]) ? trim($row[$colIdx[$field]]) : $def;
    $data = [
        'applicant_name' => $get('applicant_name') ?: $get('name') ?: 'Unknown',
        'mobile' => $get('mobile') ?: '01700000000',
        'received_date' => $get('received_date') ?: date('Y-m-d'),
        'passport_no' => $get('passport_no'),
        'district' => $get('district'),
        'thana' => $get('thana'),
        'father_name' => $get('father_name'),
        'nid_no' => $get('nid_no'),
        'govt_fee' => is_numeric($get('govt_fee')) ? $get('govt_fee') : 0,
        'service_charge' => is_numeric($get('service_charge')) ? $get('service_charge') : (is_numeric($get('total_fee'))?$get('total_fee'):0),
        'other_fee' => is_numeric($get('other_fee')) ? $get('other_fee') : 0,
        'paid_amount' => is_numeric($get('paid_amount')) ? $get('paid_amount') : 0,
        'status' => $get('status') ?: 'Receive',
        'present_address' => $get('present_address') ?? $get('address'),
    ];
    // Normalize date
    foreach (['received_date','dob'] as $d) {
        if (!empty($data[$d])) {
            $ts = strtotime($data[$d]);
            if ($ts) $data[$d] = date('Y-m-d', $ts);
            else $data[$d] = date('Y-m-d');
        }
    }
    // Tracking ID: preserve if valid
    $tracking = $get('tracking_id');
    if ($tracking && preg_match('/^PCC-/', $tracking)) {
        $data['_tracking'] = $tracking;
        // Try insert preserving ID
        if (!$dryRun) {
            try {
                // Check duplicate
                $chk=$pdo->prepare("SELECT id FROM pcc_cases WHERE tracking_id=? LIMIT 1");
                $chk->execute([$tracking]);
                if ($chk->fetchColumn()) { $failed++; $errors[]=['row'=>$total,'tracking'=>$tracking,'error'=>'duplicate tracking']; continue; }
                // Use service but override tracking
                // Manual insert preserving tracking
                $pdo->beginTransaction();
                $govt=(float)$data['govt_fee']; $service=(float)$data['service_charge']; $other=(float)$data['other_fee'];
                $totalFee = ($service ?: $govt+$other) ?: 0;
                $paid=(float)$data['paid_amount']; $due=$totalFee-$paid;
                $pdo->prepare("INSERT INTO pcc_cases (tracking_id, applicant_name, father_name, mobile, nid_no, passport_no, thana, district, present_address, status, received_date, govt_fee, service_charge, other_fee, total_fee, paid_amount, due_amount, legacy_id, legacy_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$tracking,$data['applicant_name'],$data['father_name'],$data['mobile'],$data['nid_no'],$data['passport_no'],$data['thana'],$data['district'],$data['present_address'],$data['status'],$data['received_date'],$govt,$service,$other,$totalFee,$paid,$due,$tracking,json_encode(array_combine($header,$row))]);
                $pdo->commit(); $success++;
            } catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); $failed++; $errors[]=['row'=>$total,'error'=>$e->getMessage()]; }
        } else {
            $success++;
        }
    } else {
        // No tracking, let service generate
        if (!$dryRun) {
            try { $svc->create($data); $success++; } catch(Exception $e){ $failed++; $errors[]=['row'=>$total,'error'=>$e->getMessage()]; }
        } else $success++;
    }
}
fclose($handle);
if (!$dryRun) {
    $pdo->prepare("INSERT INTO migration_logs (source,file_name,total_rows,success,failed,errors) VALUES (?,?,?,?,?,?)")
        ->execute(['sheets', basename($file), $total, $success, $failed, json_encode($errors)]);
}
echo "Import ".($dryRun?'DRY RUN ':'')."complete: total $total, success $success, failed $failed\n";
if ($failed) { echo "Errors:\n"; foreach(array_slice($errors,0,10) as $e) echo json_encode($e, JSON_UNESCAPED_UNICODE)."\n"; }
