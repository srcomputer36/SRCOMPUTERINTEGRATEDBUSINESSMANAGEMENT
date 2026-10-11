<?php
// Unified Accounting Engine — double entry
class AccountingService {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo = $pdo; }

    // Post a generic transaction with entries. Ensures debit==credit.
    // $entries: [['account_id'=>?,'debit'=>x,'credit'=>y,'description'=>..], ...]
    public function post(string $source_module, $source_id, string $type, float $amount, array $entries, array $meta=[]): int {
        if (empty($entries)) throw new Exception("No entries");
        $sumD = 0; $sumC = 0;
        foreach ($entries as $e){ $sumD += (float)($e['debit']??0); $sumC += (float)($e['credit']??0); }
        if (abs($sumD - $sumC) > 0.009) throw new Exception("Unbalanced transaction: debit $sumD != credit $sumC");
        $txn_no = generate_txn_no($this->pdo);
        $date = $meta['transaction_date'] ?? date('Y-m-d');
        $account_id = $meta['account_id'] ?? null;
        $customer_id = $meta['customer_id'] ?? null;
        $supplier_id = $meta['supplier_id'] ?? null;
        $payment_method = $meta['payment_method'] ?? null;
        $reference = $meta['reference'] ?? null;
        $description = $meta['description'] ?? null;
        $created_by = $_SESSION['user_id'] ?? null;
        $wasInTxn = $this->pdo->inTransaction();
        if (!$wasInTxn) $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO transactions (transaction_no, source_module, source_id, source_ref, transaction_date, account_id, customer_id, supplier_id, type, amount, payment_method, reference, description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$txn_no, $source_module, $source_id, $meta['source_ref']??null, $date, $account_id, $customer_id, $supplier_id, $type, $amount, $payment_method, $reference, $description, $created_by]);
            $txnId = (int)$this->pdo->lastInsertId();
            $stmt2 = $this->pdo->prepare("INSERT INTO transaction_entries (transaction_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)");
            foreach ($entries as $en){
                $stmt2->execute([$txnId, $en['account_id'] ?? null, (float)($en['debit']??0), (float)($en['credit']??0), $en['description']??null]);
            }
            if (!$wasInTxn) $this->pdo->commit();
            return $txnId;
        } catch (Exception $e){
            if (!$wasInTxn && $this->pdo->inTransaction()) $this->pdo->rollBack();
            // if duplicate source (idempotency) — return existing
            if (strpos($e->getMessage(),'uq_source')!==false || $e->getCode()=='23000'){
                // try fetch existing
                $q=$this->pdo->prepare("SELECT id FROM transactions WHERE source_module=? AND source_id=? AND type=?");
                $q->execute([$source_module,$source_id,$type]);
                $id=$q->fetchColumn();
                if($id) return (int)$id;
            }
            throw $e;
        }
    }

    // Convenience: PCC payment
    public function postPccPayment(int $caseId, float $amount, array $meta): int {
        // Double entry: Debit Account (Cash/Bank) Credit Receivable (PCC income receivable)
        // We model receivable as logical: use transaction type income? For simplicity:
        // Debit Cash account, Credit Income (Service Revenue) if you want direct, but spec says due = receivable
        // We'll do: Debit account_id, Credit null (receivable placeholder) — entries balanced
        $account_id = $meta['account_id'] ?? $this->defaultAccountId();
        return $this->post('pcc', $caseId, 'receivable', $amount, [
            ['account_id'=>$account_id, 'debit'=>$amount, 'credit'=>0, 'description'=>'PCC Payment Received'],
            ['account_id'=>null, 'debit'=>0, 'credit'=>$amount, 'description'=>'PCC Receivable'],
        ], $meta);
    }
    public function postSale(int $saleId, float $total, array $meta): int {
        $account_id = $meta['account_id'] ?? null; // for paid portion maybe
        $customer_id = $meta['customer_id'] ?? null;
        // Sale: Debit Receivable (customer), Credit Sales Revenue
        // If paid partially, separate payment txn handles cash.
        return $this->post('sale', $saleId, 'receivable', $total, [
            ['account_id'=>null, 'debit'=>$total, 'credit'=>0, 'description'=>'Sale Receivable'],
            ['account_id'=>null, 'debit'=>0, 'credit'=>$total, 'description'=>'Sales Revenue'],
        ], array_merge($meta,['customer_id'=>$customer_id]));
    }
    public function postSalePayment(int $saleId, float $amount, array $meta): int {
        $account_id = $meta['account_id'] ?? $this->defaultAccountId();
        return $this->post('sale_payment', $saleId, 'income', $amount, [
            ['account_id'=>$account_id, 'debit'=>$amount, 'credit'=>0, 'description'=>'Sale Payment Cash'],
            ['account_id'=>null, 'debit'=>0, 'credit'=>$amount, 'description'=>'Sale Receivable Clear'],
        ], $meta);
    }
    public function postPurchase(int $purchaseId, float $total, array $meta): int {
        return $this->post('purchase', $purchaseId, 'payable', $total, [
            ['account_id'=>null, 'debit'=>$total, 'credit'=>0, 'description'=>'Purchase Expense/Stock'],
            ['account_id'=>null, 'debit'=>0, 'credit'=>$total, 'description'=>'Payable to Supplier'],
        ], $meta);
    }
    public function postExpense(int $expenseId, float $amount, array $meta): int {
        $account_id = $meta['account_id'] ?? $this->defaultAccountId();
        return $this->post('expense', $expenseId, 'expense', $amount, [
            ['account_id'=>null, 'debit'=>$amount, 'credit'=>0, 'description'=>'Expense'],
            ['account_id'=>$account_id, 'debit'=>0, 'credit'=>$amount, 'description'=>'Cash/Bank Out'],
        ], $meta);
    }
    public function postIncome(int $incomeId, float $amount, array $meta): int {
        $account_id = $meta['account_id'] ?? $this->defaultAccountId();
        return $this->post('income', $incomeId, 'income', $amount, [
            ['account_id'=>$account_id, 'debit'=>$amount, 'credit'=>0, 'description'=>'Cash In'],
            ['account_id'=>null, 'debit'=>0, 'credit'=>$amount, 'description'=>'Income'],
        ], $meta);
    }
    public function postTransfer(int $fromAcc, int $toAcc, float $amount, array $meta): int {
        $id = $meta['transfer_id'] ?? time();
        return $this->post('transfer', $id, 'transfer', $amount, [
            ['account_id'=>$toAcc, 'debit'=>$amount, 'credit'=>0, 'description'=>'Transfer In'],
            ['account_id'=>$fromAcc, 'debit'=>0, 'credit'=>$amount, 'description'=>'Transfer Out'],
        ], $meta);
    }
    public function reverseTransaction(int $txnId, string $reason=''): int {
        $orig = $this->pdo->prepare("SELECT * FROM transactions WHERE id=?");
        $orig->execute([$txnId]);
        $o = $orig->fetch();
        if (!$o) throw new Exception("Transaction not found");
        // fetch entries
        $ents = $this->pdo->prepare("SELECT * FROM transaction_entries WHERE transaction_id=?");
        $ents->execute([$txnId]);
        $rows = $ents->fetchAll();
        $revEntries=[];
        foreach($rows as $r){ $revEntries[]=['account_id'=>$r['account_id'],'debit'=>(float)$r['credit'],'credit'=>(float)$r['debit'],'description'=>'Reversal: '.$r['description']]; }
        $newId = $this->post($o['source_module'].'_reversal', $txnId, $o['type'], (float)$o['amount'], $revEntries, [
            'transaction_date'=>date('Y-m-d'),
            'description'=>'Reversal of '.$o['transaction_no'].' - '.$reason,
            'reference'=>$o['reference'],
        ]);
        $this->pdo->prepare("UPDATE transactions SET reversal_id=? WHERE id=?")->execute([$newId,$txnId]);
        return $newId;
    }
    private function defaultAccountId(): ?int {
        $id = $this->pdo->query("SELECT id FROM accounts WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
        return $id ? (int)$id : null;
    }
}
