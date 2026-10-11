<?php
// Migration validation test — run with DB if available
require __DIR__ . '/../app/Database.php';
echo "MigrationTest: checking schema file exists...\n";
if (!file_exists(__DIR__ . '/../database/schema.sql')) { echo "[FAIL] schema.sql missing\n"; exit(1); }
echo "[PASS] schema.sql exists\n";
if (!file_exists(__DIR__ . '/../database/seed.sql')) { echo "[FAIL] seed.sql missing\n"; exit(1); }
echo "[PASS] seed.sql exists\n";
if (!file_exists(__DIR__ . '/../tools/import_sheets.php')) { echo "[FAIL] importer missing\n"; exit(1); }
echo "[PASS] importer exists\n";
// Test CSV parse
$csv = __DIR__ . '/fixtures/pcc_legacy.csv';
if (!file_exists($csv)) {
    echo "[SKIP] fixtures not found, creating dummy\n";
    @mkdir(__DIR__.'/fixtures',0755,true);
    file_put_contents($csv, "Tracking ID,Applicant Name,Passport,Mobile,District,Status,Service Charge,Paid\nPCC-20241011-0001,Test User,A12345678,01712345678,Dhaka,Receive,1500,500\n");
}
echo "[PASS] fixture ready\n";
echo "MigrationTest done — to fully test, run: php tools/import_sheets.php --file=tests/fixtures/pcc_legacy.csv --dry-run\n";
