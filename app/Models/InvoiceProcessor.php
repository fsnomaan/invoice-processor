<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

class InvoiceProcessor
{
    /** @var BankStatement */
    private $bs;

    /** @var OpenInvoice */
    private $openInvoice;

    /** @var array */
    private $invoiceNumbers = [];

    /** @var array  */
    private $paymentRefs = [];

    /** @var array  */
    private $export = [];

    private $bsIndex = 0;

    /** @var CompanyName */
    private $companyName;

    /** @var BankAccount */
    private $bankAccount;

    /** @var  array */
    private $bankAccountMap;

    /** @var string */
    private $message = '';

    /** @var bool */
    private $isPartialPayment = false;

    /** @var int $userId */
    private $userId;

    public function __construct(
        BankStatement $bs,
        OpenInvoice $openInvoice,
        CompanyName $companyName,
        BankAccount $bankAccount
    ) {
        $this->export = [];
        $this->bs = $bs;
        $this->openInvoice = $openInvoice;
        $this->companyName = $companyName;
        $this->bankAccount = $bankAccount;

    }

    public function processInvoice(int $userId) :array
    {
        $this->userId = $userId;
        $this->bankAccountMap = $this->bankAccount->getAccountsMap($this->userId);

        $this->invoiceNumbers = $this->openInvoice->getAllInvoiceNumbers()->toArray();
        $this->paymentRefs = $this->bs->getAllPaymentRefs()->toArray();

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $this->matchByInvoiceNumber($invoiceNumber);
        }

        $this->matchByPartialInvoiceNumber();

        $this->matchByInvoiceTotal();


//        dump($this->invoiceNumbers);
//        dd($this->paymentRefs);

        $bsRows = $this->bs->getByPaymentRef($this->paymentRefs);
        foreach ($bsRows as $bsRow) {
            ++$this->bsIndex;
            $this->exportRowsWithNoMatch($bsRow, null);
        }


//        foreach ($this->invoices as $key => $invoice) {
//            $this->createExportRowWithPartialInvoice($invoice, $this->invoices);
//        }
//
//        $this->exportRowsForMissingInvoices();
//        dd($this->export);

        return $this->export;
    }


    private function matchByInvoiceNumber($invoiceNumber, bool $partial=false)
    {
        $bsRow = $this->bs->getRowsLikeInvoice($invoiceNumber);

        if (! empty($bsRow) && in_array($bsRow->payment_ref, $this->paymentRefs)) {
            ++$this->bsIndex;

            $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $partial);

            if (count($matchingInvoiceNumbers) == 1) {
                $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                if ( $this->matchSingleInvoiceTotal($openInvoiceRow, $bsRow) ) {
                    $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                } else {
                    $this->message = 'No Match Found';
                    $this->isPartialPayment = false;
                    $this->exportRowsWithNoMatch($bsRow, null);
                }
            } else {
                $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoiceNumbers);
                $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows);

                if ( $this->isTotalMatches((float)$bsRow->amount, (float)$openInvoiceTotal)) {
                    foreach($openInvoiceRows as $openInvoiceRow) {
                        $this->message = 'Invoice Number';
                        $this->isPartialPayment = false;
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                    }
                } else {
                    foreach($openInvoiceRows as $openInvoiceRow) {
                        $this->message = 'Invoice Number';
                        $this->isPartialPayment = false;
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                    }
                    $differenceInTotal = $this->getDifferenceInTotal((float)$bsRow->amount, $openInvoiceTotal);
                    $this->message = 'No Match Found';
                    $this->isPartialPayment = false;
                    $this->exportRowsWithNoMatch($bsRow, $differenceInTotal);
                }
            }
        }
    }

    private function matchSingleInvoiceTotal($openInvoiceRow, $bsRow, int $bankCharge=30)
    {
        $this->message = 'Invoice Number';
        if ($openInvoiceRow->open_amount == $bsRow->amount) {
            $this->isPartialPayment = false;
            return true;
        } elseif ($bsRow->amount >= ($openInvoiceRow->open_amount - $bankCharge) &&
            $bsRow->amount <= ($openInvoiceRow->open_amount + $bankCharge) ) {
            $this->isPartialPayment = true;
            return true;
        }

        return false;
    }

    private function matchByPartialInvoiceNumber()
    {
        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $this->matchByInvoiceNumber($this->getInvoicePart($invoiceNumber), true);
        }
    }

    private function matchByInvoiceTotal()
    {
        $bsRows = $this->bs->getByPaymentRef($this->paymentRefs);
        foreach ($bsRows as $bsRow) {
            $invoice = $this->openInvoice->getInvoiceByAmount((float)$bsRow->amount);
            if (count($invoice) == 1) {
                ++$this->bsIndex;
                $this->exportRowsWithMatch($bsRow, $invoice[0]);
            }
        }
    }

    private function getMatchingInvoiceNumbers(BankStatement $bsRow, bool $partial=false) : array
    {
        $matchingInvoices = [];

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            if ($partial) {
                $invoiceNumber = $this->getInvoicePart($invoiceNumber);
            }
            if (strpos(strtolower($bsRow->payment_ref), trim(strtolower($invoiceNumber)) ) !== false) {
                $matchingInvoices[] = $invoiceNumber;
            }
        }

        return $matchingInvoices;
    }

    private function getInvoicePart(string $invoiceNumber): ?int
    {
        preg_match('/[1-9]\d+/', $invoiceNumber, $invoicePart);
        if ( isset($invoicePart[0]) && ! empty($invoicePart[0])) {
            return (int)$invoicePart;
        }

        return null;
    }

    private function getOpenInvoicesTotal(Collection $openInvoiceRows) : float
    {
        $openInvoiceTotal = 0;
        foreach($openInvoiceRows as $openInvoiceRow) {
            $openInvoiceTotal += (float) $openInvoiceRow->open_amount;
        }

        return $openInvoiceTotal;
    }

    private function isTotalMatches(float $bsTotal, float $openInvoiceTotal) : bool
    {
        return $bsTotal == $openInvoiceTotal;
    }

    private function exportRowsWithMatch(BankStatement $bsRow, OpenInvoice $openInvoiceRow)
    {
        $this->export[] = [
            $this->bsIndex,
            $bsRow->transaction_date,
            $openInvoiceRow->customer_account,
            $openInvoiceRow->invoice_number,
            $bsRow->currency,
            $openInvoiceRow->open_amount,
            $bsRow->payment_ref,
            $bsRow->payee_name,
            $openInvoiceRow->customer_name,
            $this->message,
            $bsRow->original_amount,
            $openInvoiceRow->open_amount,
            $this->isPartialPayment ? 'Yes' : 'No'
        ];

        $this->deleteElement($bsRow->payment_ref, $this->paymentRefs);
        $this->deleteElement($openInvoiceRow->invoice_number, $this->invoiceNumbers);
    }

    private function exportRowsWithNoMatch(BankStatement $bsRow, float $difference=null)
    {
        $this->export[] = [
            $this->bsIndex,
            $bsRow->transaction_date,
            '',
            '',
            $bsRow->currency,
            $difference,
            $bsRow->payment_ref,
            $bsRow->payee_name,
            '',
            $this->message,
            $bsRow->original_amount,
            '',
            $this->isPartialPayment ? 'Yes' : 'No'
        ];
    }

/////////////////////////////////////////


//    private function createExportRow(string $invoiceNumber, array &$invoices)
//    {
//        if (! in_array($invoiceNumber, $invoices) ) {
//            return;
//        }
//
//        $bsRow = $this->bs->getRowsLikeInvoice($invoiceNumber);
//
//        if (! empty($bsRow) ) {
//            $matchingInvoices = $this->getMatchingInvoices($bsRow, $invoices);
//            $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoices);
//            $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows);
//
//            if ( $this->isTotalMatches($this->getBsAmount($bsRow), (float) $openInvoiceTotal)) {
//                $this->exportRowsForMatchingTotal($bsRow, $openInvoiceRows);
//            } else {
//                $this->exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal);
//            }
//            unset($openInvoiceRows);
//        }
//
//        unset($bsRow);
//    }
//
//    private function getMatchingInvoices(BankStatement $bsRow, array &$invoices) : array
//    {
//        $matchingInvoices = [];
//
//        foreach ($invoices as $key => $invoice) {
//
//            if (strpos($bsRow->purpose_of_use, trim($invoice) ) !== false) {
//                $matchingInvoices[] = $invoice;
//            }
//        }
//
//        return $matchingInvoices;
//    }
//
//    private function getOpenInvoicesTotal(Collection $openInvoiceRows) : float
//    {
//        $openInvoiceTotal = 0;
//        foreach($openInvoiceRows as $openInvoiceRow) {
//            $openInvoiceTotal += (float) $openInvoiceRow->amount_transaction;
//        }
//
//        return $openInvoiceTotal;
//    }
//
//    private function exportRowsForMatchingTotal(BankStatement $bsRow, Collection $openInvoiceRows)
//    {
//        foreach($openInvoiceRows as $openInvoiceRow) {
//            $this->note .= 'Matched';
//            $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
//        }
//    }
//
//    private function exportRowsForUnmatchedTotal(BankStatement $bsRow, Collection $openInvoiceRows, float $openInvoiceTotal)
//    {
//        $differenceInTotal = $this->getDifferenceInTotal($this->getBsAmount($bsRow), $openInvoiceTotal);
//        $differenceInvoices = $this->openInvoice->getInvoiceFromTotalAndName((float)$differenceInTotal, $openInvoiceRows[0]->name);
//
//        foreach($openInvoiceRows as $openInvoiceRow) {
//            $this->note .= 'Matched';
//            $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
//        }
//
//        if ( $differenceInvoices->isNotEmpty() && count($differenceInvoices) == 1 ){
//            $this->note .= 'Matched';
//            $differenceInvoice = $differenceInvoices->first();
//            $this->exportRowsWithMatch($bsRow, $differenceInvoice);
//        } else {
//            if ( $differenceInTotal == 0 ) {
//                return;
//            }
//            $this->note = 'Please find invoice manually';
//            $this->exportRowsWithDifference($bsRow, $differenceInTotal);
//        }
//
//        unset($differenceInvoices);
//    }
//
//    private function createExportRowWithPartialInvoice(string $invoiceNumber, array &$invoices)
//    {
//        if (! in_array($invoiceNumber, $invoices) ) {
//            return;
//        }
//
//        $parts = explode('-', $invoiceNumber);
//
//        if ( isset($parts[1])) {
//            $bsRow = $this->bs->getRowsLikeInvoice($parts[1]);
//
//            if (! empty($bsRow)) {
//                $matchingInvoices = $this->getPartialMatchingInvoices($bsRow, $invoices);
//                $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoices);
//                $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows);
//
//                if ( $this->isTotalMatches($this->getBsAmount($bsRow), (float) $openInvoiceTotal)) {
//                    foreach($openInvoiceRows as $openInvoiceRow) {
//                        $this->note .= 'Matched by partial invoice number';
//                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
//                    }
//                }
//                unset($openInvoiceRow);
//            }
//            unset($bsRow);
//        }
//    }
//
//    private function getPartialMatchingInvoices(BankStatement $bsRow, array &$invoices) : array
//    {
//        $matchingInvoices = [];
//
//        foreach ($invoices as $key => $invoice) {
//            if (! empty($invoice)) {
//                $parts = explode('-', $invoice);
//
//                if ( isset($parts[1])) {
//                    $needle = $parts[1];
//                    if (strpos($bsRow->purpose_of_use, trim($needle) ) !== false) {
//                        $matchingInvoices[] = $invoice;
//                        unset($invoices[$key]);
//                    }
//                }
//
//            }
//        }
//
//        return $matchingInvoices;
//    }
//
//    private function exportRowsForMissingInvoices()
//    {
//        $unmatchedBsRows = $this->bs->getUnmatchedRows($this->matchedBsRows);
//
//        foreach ($unmatchedBsRows as $unmatchedBsRow) {
//            /** @var Collection $invoices */
//
//            $amount = $this->getBsAmount($unmatchedBsRow);
//            $invoices = $this->openInvoice->getInvoiceByAmount($amount, array_column($this->export, 3));
//
//            if (count($invoices) == 1 ) {
//                $invoice = $invoices->first();
//                if ($amount == $invoice->amount_transaction) {
//                    $this->note .= 'Invoice matched based on total';
//                    $this->exportRowsWithMatch($unmatchedBsRow, $invoice);
//                } else {
//                    $this->note = 'Please find invoice manually';
//                    $this->exportRowsWithDifference($unmatchedBsRow, ($amount - (float)$invoice->amount_transaction));
//                }
//
//            } elseif (count($invoices) > 1 ) {
//                $multipleInvoices = array_column($invoices->toArray(), 'invoice');
//
//                /** @var Collection $invoiceRowByName */
//                $invoiceRowByName = $this->openInvoice->getInvoiceByMatchingName(
//                    $this->getCompanyCustomerName($unmatchedBsRow->company_customer),
//                    $multipleInvoices
//                );
//                if ($invoiceRowByName->isNotEmpty()) {
//                    $this->processRowsWithSimilarName($unmatchedBsRow, $invoiceRowByName);
//                } else {
//                    $this->note = 'Multiple invoice found';
//                    $this->exportRowsWithNoMatch($unmatchedBsRow);
//                }
//
//                unset($invoiceRowByName);
//
//            } else {
//                $this->note = 'Missing invoice details';
//                $this->exportRowsWithNoMatch($unmatchedBsRow);
//            }
//
//            unset($invoices);
//        }
//
//        unset($differenceInvoices);
//    }
//
//    private function processRowsWithSimilarName(BankStatement $unmatchedBsRow, Collection $invoiceRows)
//    {
//        $invoiceRow = null;
//
//        $amount = $this->getBsAmount($unmatchedBsRow);
//
//        if (count($invoiceRows) > 1) {
//            $found = $this->getRowsWithBSAmount($amount, $invoiceRows);
//
//            if ( count($found) == 1) {
//                /** @var OpenInvoice $invoiceRow */
//                $invoiceRow = $found[0];
//                $this->note = 'Invoice matched based on similar name';
//                $this->exportRowsWithMatch($unmatchedBsRow, $invoiceRow);
//            } elseif ( count($found) > 1 ) {
//                $this->note = "Multiple Invoice matched based on similar name. \nPlease manually select invoice.";
//                /** @var OpenInvoice $invoiceRow */
//                $invoiceRow = $found[0];
//                $this->exportRowsWithMultipleMatch($unmatchedBsRow, $invoiceRow);
//            } else {
//                $this->note = 'Missing invoice details';
//                $this->exportRowsWithNoMatch($unmatchedBsRow);
//            }
//        } elseif (count($invoiceRows) == 1) {
//            $openInvoiceRow = $invoiceRows->first();
//            if ( $this->isTotalMatches( $amount, (float)$openInvoiceRow->amount_transaction) ) {
//                $this->note = 'Invoice matched based on similar name';
//                $this->exportRowsWithMatch($unmatchedBsRow, $openInvoiceRow);
//            } else {
//                $this->note = 'Missing invoice details';
//                $this->exportRowsWithNoMatch($unmatchedBsRow);
//            }
//        }
//    }
//
//    private function getRowsWithBSAmount(float $bsAmount, Collection $invoiceRows) : array
//    {
//        $found = [];
//        foreach ($invoiceRows as $row) {
//            if ( (float)$row->amount_transaction == $bsAmount) {
//                $found[] = $row;
//            }
//        }
//
//        return $found;
//    }
//
//    private function exportRowsWithMatch(BankStatement $bsRow, OpenInvoice $invoiceRow)
//    {
//        $this->matchedBsRows[] = $bsRow->id;
//        $this->updateNote($bsRow, $invoiceRow);
//
//        $this->export[] = [
//            $bsRow->trans_date,
//            $invoiceRow->customer_account,
//            $invoiceRow->invoice,
//            '',
//            $invoiceRow->amount_transaction,
//            $this->getCurrency($bsRow),
//            $bsRow->company_customer,
//            $bsRow->trans_date,
//            $this->getBankAccountId($bsRow->datev_account_number),
//            $this->note,
//            $invoiceRow->name,
//            $bsRow->amount,
//            $bsRow->purpose_of_use
//
//        ];
//
//        $this->deleteElement($invoiceRow->invoice, $this->invoices);
//        $this->note = '';
//    }
//
//    private function exportRowsWithMultipleMatch(BankStatement $bsRow, OpenInvoice $invoiceRow)
//    {
//        $this->matchedBsRows[] = $bsRow->id;
//        $this->updateNote($bsRow, $invoiceRow);
//
//        $this->export[] = [
//            $bsRow->trans_date,
//            $invoiceRow->customer_account,
//            '',
//            '',
//            $invoiceRow->amount_transaction,
//            $this->getCurrency($bsRow),
//            $bsRow->company_customer,
//            $bsRow->trans_date,
//            $this->getBankAccountId($bsRow->datev_account_number),
//            $this->note,
//            $invoiceRow->name,
//            $bsRow->amount,
//            $bsRow->purpose_of_use
//        ];
//
//        $this->note = '';
//    }
//
//    private function exportRowsWithDifference(BankStatement $bsRow, float $difference)
//    {
//        $currency = empty($bsRow->original_currency) ? $bsRow->currency : $bsRow->original_currency;
//
//        $this->matchedBsRows[] = $bsRow->id;
//
//        $this->export[] = [
//            $bsRow->trans_date,
//            'Not found',
//            'Not found',
//            '',
//            round($difference, 2),
//            $currency,
//            $bsRow->company_customer,
//            $bsRow->trans_date,
//            $this->getBankAccountId($bsRow->datev_account_number),
//            $this->note,
//            'Not found',
//            $bsRow->amount,
//            $bsRow->purpose_of_use
//        ];
//
//        $this->note = '';
//    }
//
//    private function exportRowsWithNoMatch(BankStatement $bsRow)
//    {
//        $currency = empty($bsRow->original_currency) ? $bsRow->currency : $bsRow->original_currency;
//
//        $this->matchedBsRows[] = $bsRow->id;
//
//        $this->export[] = [
//            $bsRow->trans_date,
//            'Not found',
//            'Not found',
//            '',
//            $bsRow->amount,
//            $currency,
//            $bsRow->company_customer,
//            $bsRow->trans_date,
//            $this->getBankAccountId($bsRow->datev_account_number),
//            $this->note,
//            'Not found',
//            $bsRow->amount,
//            $bsRow->purpose_of_use
//        ];
//
//        $this->note = '';
//    }
//
//    private function isTotalMatches(float $bsTotal, float $openInvoiceTotal) : bool
//    {
//        return $bsTotal == $openInvoiceTotal;
//    }
//
   private function getDifferenceInTotal(float $bsTotal, float $openInvoiceTotal) : float
   {
       return round(($bsTotal - $openInvoiceTotal), 2);
   }
//
//    private function getCompanyCustomerName(string $name) : string
//    {
//        $map = $this->companyName->getNamesMap($this->userId);
//
//        if (array_key_exists($name, $map) ) {
//            return $map[$name];
//        }
//
//        return $name;
//    }
//
    private function deleteElement($needle, &$haystack){
        $index = array_search($needle, $haystack);
        if($index !== false){
            unset($haystack[$index]);
        }
    }
//
//    private function getBsAmount(BankStatement $bsRow)
//    {
//        if ( $bsRow->currency == $bsRow->original_currency ) {
//            if ( $bsRow->amount != $bsRow->original_amount ) {
//                $this->note = "Bank charge adjusted\n";
//                return (float)$bsRow->amount;
//            }
//        }
//
//        if ( empty($bsRow->original_amount) ) {
//            return (float)$bsRow->amount;
//        }
//
//        return (float)$bsRow->original_amount;
//    }
//
//    private function getBankAccountId(string $accountNumber)
//    {
//        if ( isset($this->bankAccountMap[$accountNumber])) {
//            return $this->bankAccountMap[$accountNumber];
//        }
//
//        return $accountNumber;
//    }
//
//    private function getCurrency(BankStatement $bsRow)
//    {
//        return empty($bsRow->original_currency) ? $bsRow->currency : $bsRow->original_currency;
//    }
//
//    private function updateNote(BankStatement $bsRow, OpenInvoice $invoiceRow)
//    {
//        $this->note .= $bsRow->currency == $bsRow->original_currency ? '' : "\nDifferent Currency";
//        $this->note .= $invoiceRow->currency == $bsRow->original_currency ? '' : "\nDifference in invoice currency";
//    }
}
