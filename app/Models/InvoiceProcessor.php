<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
ini_set('max_execution_time', 300);

class InvoiceProcessor
{
    /** @var int */
    private $userId;

    /** @var BankStatement */
    private $bs;

    /** @var OpenInvoice */
    private $openInvoice;

    /** @var array */
    private $invoiceNumbers = [];

    /** @var array */
    private $bsRowSequence = [];

    private $uniqueCustomers = [];

    /** @var array */
    private $export = [];

    /** @var CompanyName */
    private $companyName;

    /** @var BankAccount */
    private $bankAccount;

    /** @var string */
    private $message = '';

    /** @var bool */
    private $isPartialPayment = false;

    private $isOverPayment = false;

    public function __construct(
        BankStatement $bs,
        OpenInvoice $openInvoice,
        CompanyName $companyName,
        BankAccount $bankAccount
    )
    {
        $this->export = [];
        $this->bs = $bs;
        $this->openInvoice = $openInvoice;
        $this->companyName = $companyName;
        $this->bankAccount = $bankAccount;
    }

    public function processInvoice(int $userId): array
    {
        $this->userId = $userId;

        $this->invoiceNumbers = $this->openInvoice->getAllInvoiceNumbers($userId)->toArray();
        $this->bsRowSequence = $this->bs->getSequence($userId)->toArray();

        foreach (['payment_ref', 'payee_name'] as $searchField) {
            $this->matchByInvNumberWhenStatementEqualsInvoice($searchField);
            $this->matchByInvNumberWhenStatementGreaterThanInvoice($searchField);
            $this->matchByInvNumberWhenStatementLowerThanInvoice($searchField);
            $this->matchByMultipleInvoiceWhenStatementEqualsInvoice($searchField);
            $this->matchByMultipleInvoiceWhenStatementNotEqualsInvoice($searchField);
            $this->matchByTotalWhenStatementEqualsInvoice($searchField);
            $this->matchByTotalWhenStatementEqualsInvoiceTotal($searchField);
            $this->matchByTotalWhenStatementDoesNotEqualsInvoiceTotal($searchField);
            $this->matchByAccountNameWhenStatementEqualsInvoice($searchField);
            $this->matchByAccountNameWhenStatementEqualsMultipleInvoice($searchField);
            $this->matchByAccountNameWhenStatementEqualsSumOfMultipleInvoice($searchField);
        }

        $this->exportUnmatchedStatementRows();

//        dd($this->export);
        return $this->export;
    }

    private function matchByInvNumberWhenStatementEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }
        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) == 1) {
                        $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                        if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                            $this->message = 'Invoice Number';
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                        }
                    }
                }
            }
        }
    }

    private function matchByInvNumberWhenStatementGreaterThanInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }
        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) == 1) {
                        $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                        if ((float)$bsRow->amount > (float)$openInvoiceRow->open_amount) {
                            $this->message = 'Invoice Number';
                            $this->isOverPayment = true;
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                        }
                    }
                }
            }
        }
    }

    private function matchByInvNumberWhenStatementLowerThanInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }
        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) == 1) {
                        $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                        if ((float)$bsRow->amount < (float)$openInvoiceRow->open_amount) {
                            $this->message = 'Invoice Number';
                            $this->isPartialPayment = true;
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                        }
                    }
                }
            }
        }
    }

    private function matchByMultipleInvoiceWhenStatementEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }
        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) > 1) {
                        /** @var Collection $openInvoiceRows */
                        $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoiceNumbers);
                        $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                        if ( (float)$openInvoiceTotal == (float)$bsRow->amount )  {
                            if ($this->isAllForSameAccount($openInvoiceRows)) {
                                foreach($openInvoiceRows as $openInvoiceRow) {
                                    $this->message = 'Invoice Number';
                                    $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function matchByMultipleInvoiceWhenStatementNotEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }
        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) > 1) {
                        /** @var Collection $openInvoiceRows */
                        $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoiceNumbers);
                        $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                        if ( (float)$openInvoiceTotal != (float)$bsRow->amount )  {
                            if ($this->isAllForSameAccount($openInvoiceRows)) {
                                $this->message = 'Only Customer Account';
                                $this->exportRowsWithNoMatch($bsRow, $openInvoiceRows[0]);
                            }
                        }
                    }
                }
            }
        }
    }

    private function isAllForSameAccount(Collection $invoiceRows): bool
    {
        $accounts = array_column($invoiceRows->toArray(), 'customer_account');
        if (count(array_unique($accounts)) === 1) {
            return true;
        }

        return false;
    }

    private function matchByTotalWhenStatementEqualsInvoice(string $searchField)
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $matchedInvoices = $this->getMatchedInvoiceForAmount($bsRow, $searchField);

            if ( $matchedInvoices ) {
                if (count($matchedInvoices) == 1) {
                    $this->exportRowsWithMatch($bsRow, $matchedInvoices[0]);
                }
            }
        }
    }

    private function matchByTotalWhenStatementEqualsInvoiceTotal(string $searchField)
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $matchedInvoices = $this->getMatchedInvoiceForAmount($bsRow, $searchField);

            if ( $matchedInvoices ) {
                if (count($matchedInvoices) > 1) {
                    $invoicesTotal = $this->getOpenInvoicesTotal($matchedInvoices);
                    if ((float)$invoicesTotal == (float)$bsRow->amount) {
                        foreach ($matchedInvoices as $openInvoiceRow) {
                            $this->message = 'ERP Account Name';
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                        }
                    }
                }
            }
        }
    }

    private function matchByTotalWhenStatementDoesNotEqualsInvoiceTotal(string $searchField)
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $matchedInvoices = $this->getMatchedInvoiceForAmount($bsRow, $searchField);

            if ( $matchedInvoices ) {
                if (count($matchedInvoices) > 1) {
                    $invoicesTotal = $this->getOpenInvoicesTotal($matchedInvoices);
                    if ( (float)$invoicesTotal != (float)$bsRow->amount ) {
                        $this->message = 'Customer Account Only';
                        $this->isOverPayment = true;
                        $this->exportRowsWithNoMatch($bsRow, $matchedInvoices[0]);
                    }
                }
            }
        }
    }

    private function getMatchedInvoiceForAmount(BankStatement $bsRow, string $searchField)
    {
        $invoices = $this->openInvoice->getByAmount((float)$bsRow->amount, $this->userId);
        $matchedInvoices = [];
        foreach ($invoices as $invoice) {
            $nameMap = $this->companyName->getByName($invoice->customer_name, $this->userId);
            if ($nameMap) {
                $message = 'Name map';
                $nameMap = trim($nameMap);
            } else {
                $message = 'ERP Name Map';
                $nameMap = $invoice->customer_name;

            }

            if (strpos(strtolower($bsRow->$searchField), strtolower($nameMap) ) !== false
            ) {
                $this->message = $message;
                $matchedInvoices[] = $invoice;
            }
        }
        return $matchedInvoices;
    }

    private function matchByAccountNameWhenStatementEqualsInvoice(string $searchField)
    {
        $this->uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($this->uniqueCustomers as $customerName) {
            if ( !$customerName ) return;
            $bsRows = $this->bs->findBySearchField($customerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {

                $matchedInvoices = [];
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                foreach ($openInvoiceRows as $openInvoiceRow) {
                    if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                        $matchedInvoices[] = $openInvoiceRow;
                    }
                }
                if (count($matchedInvoices) == 1) {
                    $this->message = 'ERP Account Name';
                    $this->exportRowsWithMatch($bsRow, $matchedInvoices[0]);
                    $this->deleteElement($customerName, $this->uniqueCustomers);

                }
            }
        }
    }

    private function matchByAccountNameWhenStatementEqualsMultipleInvoice(string $searchField)
    {
        foreach ($this->uniqueCustomers as $customerName) {
            if ( !$customerName ) return;
            $bsRows = $this->bs->findBySearchField($customerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $matchedInvoices = [];
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                foreach ($openInvoiceRows as $openInvoiceRow) {
                    if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                        $matchedInvoices[] = $openInvoiceRow;
                    }
                }
                if (count($matchedInvoices) > 1) {
                    $this->message = 'Customer Account Only';
                    $this->isOverPayment = true;
                    $this->exportRowsWithNoMatch($bsRow, $matchedInvoices[0]);
                    $this->deleteElement($customerName, $this->uniqueCustomers);
                }
            }
        }
    }

    private function matchByAccountNameWhenStatementEqualsSumOfMultipleInvoice(string $searchField)
    {
        foreach ($this->uniqueCustomers as $customerName) {
            if ( !$customerName ) return;
            $bsRows = $this->bs->findBySearchField($customerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);

                $invoicesTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                if ( (float)$invoicesTotal == (float)$bsRow->amount ) {
                    foreach ($openInvoiceRows as $openInvoiceRow) {
                        $this->message = 'ERP Account Name';
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                        $this->deleteElement($customerName, $this->uniqueCustomers);

                    }
                }
            }
        }
    }

    private function getOpenInvoicesTotal(array $openInvoiceRows) : float
    {
        $openInvoiceTotal = 0;
        foreach($openInvoiceRows as $openInvoiceRow) {
            $openInvoiceTotal += (float) $openInvoiceRow['open_amount'];
        }

        return $openInvoiceTotal;
    }

    private function getMatchingInvoiceNumbers(BankStatement $bsRow, string $searchField, bool $partial=false) : array
    {
        $matchingInvoiceNumbers = [];

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            if ($partial) {
                $invoicePart = $this->getInvoicePart($invoiceNumber);
                if ($invoicePart) {
                    if (strpos(strtolower($bsRow->$searchField), trim(strtolower($invoicePart)) ) !== false) {
                        $matchingInvoiceNumbers[] = $invoiceNumber;
                    }
                }
            } else {
                if ($invoiceNumber) {
                    if (strpos(strtolower($bsRow->$searchField), trim(strtolower($invoiceNumber)) ) !== false) {
                        $matchingInvoiceNumbers[] = $invoiceNumber;
                    }
                }
            }
        }

        // remove duplicate invoice number
        return array_unique($matchingInvoiceNumbers);
    }

    private function getInvoicePart(string $invoiceNumber): ?string
    {
        $partial = preg_split('/\D[0]/', $invoiceNumber);

        return end($partial);
    }

    private function exportRowsWithMatch(BankStatement $bsRow, OpenInvoice $openInvoiceRow)
    {
        if ( !in_array($openInvoiceRow->invoice_number, $this->invoiceNumbers ) ) {
            return;
        }

        $this->export[] = [
            $bsRow->sequence,
            $bsRow->transaction_date,
            $openInvoiceRow->customer_account,
            $openInvoiceRow->invoice_number,
            $bsRow->currency,
            $this->getAmount($bsRow, $openInvoiceRow),
            $bsRow->payment_ref,
            $bsRow->payee_name,
            $openInvoiceRow->customer_name,
            $this->message,
            $bsRow->original_amount,
            $openInvoiceRow->open_amount,
            $this->isPartialPayment ? 'Yes' : 'No',
            $this->isOverPayment ? 'Yes' : 'No'
        ];

        $this->deleteElement($bsRow->sequence, $this->bsRowSequence);

        if (!$this->isPartialPayment) {
            $this->deleteElement($openInvoiceRow->invoice_number, $this->invoiceNumbers);
        }
        $this->resetMessage();
    }

    private function getAmount(BankStatement $bankStatement, OpenInvoice $openInvoice): float
    {
        if ($this->isOverPayment || $this->isPartialPayment) {
            return $bankStatement->original_amount;
        }

        return $openInvoice->open_amount;
    }

    private function deleteElement($needle, &$haystack){
        $index = array_search($needle, $haystack);
        if($index !== false){
            unset($haystack[$index]);
        }
    }

    private function exportUnmatchedStatementRows()
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $this->message = 'No Match Found';
            $this->exportRowsWithNoMatch($bsRow, null);
        }
    }

    private function exportRowsWithNoMatch(BankStatement $bsRow, OpenInvoice $invoice=null)
    {
        if ( !in_array($bsRow->sequence, $this->bsRowSequence ) ) {
            return;
        }

        $this->export[] = [
            $bsRow->sequence,
            $bsRow->transaction_date,
            $invoice ? $invoice->customer_account : '',
            '',
            $bsRow->currency,
            $bsRow->original_amount,
            $bsRow->payment_ref,
            $bsRow->payee_name,
            $invoice ? $invoice->customer_name : '',
            $this->message,
            $bsRow->original_amount,
            '',
            $this->isPartialPayment ? 'Yes' : 'No',
            $this->isOverPayment ? 'Yes' : 'No'
        ];

        $this->deleteElement($bsRow->sequence, $this->bsRowSequence);
        $this->resetMessage();;
    }

    private function resetMessage()
    {
        $this->message = '';
        $this->isPartialPayment = false;
        $this->isOverPayment = false;
    }
}
