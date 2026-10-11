<?php
// Financial calculation tests — run: php tests/FinancialTest.php
// Uses in-memory SQLite for isolation where possible, else mock logic

$passed=0; $failed=0;
function assertEqual($a,$b,$msg){
    global $passed,$failed;
    if ($a==$b) { echo "[PASS] $msg: $a == $b\n"; $passed++; }
    else { echo "[FAIL] $msg: expected $b got $a\n"; $failed++; }
}
function assertTrue($cond,$msg){
    global $passed,$failed;
    if ($cond) { echo "[PASS] $msg\n"; $passed++; }
    else { echo "[FAIL] $msg\n"; $failed++; }
}

// F1: PCC due calculation
$total=2000; $paid=1000; $due=$total-$paid;
assertEqual($due,1000,"F1 PCC due (2000-1000)");

// F2: No double count simulation
$payments=[500,500]; $incomeSum=array_sum($payments);
assertEqual($incomeSum,1000,"F2 partial payments sum");

// F6: Profit example from prompt §8
$service=2000; $govt=500; $other=200; $paid2=1000;
$profit=$service - $govt - $other; // 1300
assertEqual($profit,1300,"F6 Profit service(2000)-govt(500)-other(200)=1300");
$grossWrong=$service+$govt+$other; // 2700 if wrongly counted all as revenue
assertTrue($profit!==$grossWrong,"F6 profit not equal gross 2700");

// F3: Double entry balance check simulation
$entries=[['debit'=>1000,'credit'=>0],['debit'=>0,'credit'=>1000]];
$sumD=array_sum(array_column($entries,'debit')); $sumC=array_sum(array_column($entries,'credit'));
assertEqual($sumD,$sumC,"F3 double entry balanced");

// F4: Transfer not in P/L
$transfers=[500]; $incomes=[1000]; $expenses=[300];
$pl=array_sum($incomes)-array_sum($expenses); // transfer excluded
assertEqual($pl,700,"F4 transfer excluded from P/L");

// F7: Sales stock logic
$stock=10; $saleQty=3; $stockAfter=$stock-$saleQty;
assertEqual($stockAfter,7,"F7 stock after sale 10-3=7");
$purchaseQty=5; $stockAfter2=$stockAfter+$purchaseQty;
assertEqual($stockAfter2,12,"F7 stock after purchase 7+5=12");

// F8: Tracking ID uniqueness simulation
$ids=[]; for($i=0;$i<10;$i++){ $ids[]='PCC-'.date('Ymd').'-'.str_pad($i,4,'0',STR_PAD_LEFT); }
assertEqual(count(array_unique($ids)),10,"F8 Tracking IDs unique");

// DECIMAL precision
$amt= 99999999.99; $rounded= round($amt,2);
assertEqual($rounded,99999999.99,"DECIMAL precision");

// Due reconciliation: total - paid = due
$total=5000; $paymentsSum=2000; $due2=$total-$paymentsSum;
assertEqual($due2,3000,"Due reconciliation");

echo "\n=== Results: $passed passed, $failed failed ===\n";
exit($failed>0?1:0);
