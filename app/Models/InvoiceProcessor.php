<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

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

    /** @var array  */
    private $bsRowSequence = [];

    /** @var array  */
    private $export = [];

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
//        $this->bankAccountMap = $this->bankAccount->getAccountsMap($this->userId);

        $this->invoiceNumbers = $this->openInvoice->getAllInvoiceNumbers($userId)->toArray();
        $this->bsRowSequence = $this->bs->getSequence($userId)->toArray();

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $this->matchByInvoiceNumber($invoiceNumber);
        }

        $this->matchByPartialInvoiceNumber();

        $this->matchByInvoiceTotalOrName();

        $this->exportUnmatchedStatementRows();

//        dd($this->export);
        return $this->export;
    }

    private function matchByInvoiceNumber($invoiceNumber, bool $partial=false)
    {

        if ( empty($this->bsRowSequence) ) {
            return;
        }

        $bsRow = $this->bs->getByInvoiceNumber($invoiceNumber, $this->userId);

        if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {

            $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $partial);

            // if only one match found
            if (count($matchingInvoiceNumbers) == 1 && !$partial) {
                $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);

                if ( $this->matchSingleInvoiceTotal($openInvoiceRow, $bsRow) ) {
                    $this->message = 'Invoice Number';
                    $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                }
                // if more than one match found
            } else {
                $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoiceNumbers);
                $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows);

                if ( $this->isTotalMatches((float)$bsRow->amount, (float)$openInvoiceTotal)) {
                    foreach($openInvoiceRows as $openInvoiceRow) {
                        $this->message = empty($partial) ? 'Invoice Number' : 'Partial Invoice Number';
                        $this->isPartialPayment = false;
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                    }
                } else {
                    if (! $partial) {
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
    }

    private function matchSingleInvoiceTotal($openInvoiceRow, $bsRow, int $bankCharge=30)
    {
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
            $partInvoice = $this->getInvoicePart($invoiceNumber);
            if (! is_null($partInvoice)) {
                $this->matchByInvoiceNumber($partInvoice, true);
            }
        }
    }

    private function matchByInvoiceTotalOrName()
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $invoice = $this->openInvoice->getInvoiceByAmount((float)$bsRow->amount);
            if ( count($invoice) == 1 ) {
                $this->isPartialPayment = false;
                $this->message = 'Invoice Total';
                $this->matchByFirstWord($bsRow, $invoice[0]);
            } elseif ( count($invoice) > 1 ) {
                $this->matchByCompanyName($bsRow, $invoice);
            }
        }
    }

    private function matchByFirstWord(BankStatement $bsRow, OpenInvoice $invoice)
    {
        $needle = strtolower($this->getFirstWord($bsRow->payee_name));
        if (! $needle) {
            return;
        }

        if (strpos(strtolower($invoice->customer_name), $needle ) !== false) {
                $this->exportRowsWithMatch($bsRow, $invoice[0]);
        }
    }

    private function getFirstWord(string $line): string
    {
        $words = explode(' ', trim($line) )[0]; // 1ab&c
        preg_match('/\D+/', $words, $matches);
        if ($matches) {
            $words = end($matches); // ab&c
            $words = preg_replace('/[^A-Za-z\-]/', ' ', $words); // ab c
            $word = $words; // ab
            return $word . ' ';
        }
        return '';
    }

    private function matchByCompanyName(BankStatement $bsRow, Collection $invoices)
    {
        $matchedInvoices = [];

        foreach ($invoices as $invoice) {
            $nameMap = $this->companyName->getByName($invoice->customer_name, $this->userId);
            if ($nameMap) {
                $nameMap = trim($nameMap);
                $message = 'Name Mapping';
            } else {
                $nameMap = $invoice->customer_name;
                $message = 'Match By Name';
            }

            if (strpos(strtolower($bsRow->payment_ref), strtolower($nameMap) ) !== false ||
                strpos(strtolower($bsRow->payee_name), strtolower($nameMap) )  !== false
            ) {
                $this->message = $message;
                $matchedInvoices[] = $invoice;
            }
        }

        if ( $matchedInvoices ) {
            if (count($matchedInvoices) > 1) {
                $this->message = 'Multiple Invoices';
                $this->exportRowsWithNoMatch($bsRow, null, $matchedInvoices[0]);
            } else{
                $this->exportRowsWithMatch($bsRow, $matchedInvoices[0]);
            }
        }
    }

    private function getMatchingInvoiceNumbers(BankStatement $bsRow, bool $partial=false) : array
    {
        $matchingInvoiceNumbers = [];

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            if ($partial) {
                $invoicePart = $this->getInvoicePart($invoiceNumber);
                if ($invoicePart) {
                    if (strpos(strtolower($bsRow->payment_ref), trim(strtolower($invoicePart)) ) !== false) {
                        $matchingInvoices[] = $invoiceNumber;
                    } elseif (!$bsRow->payment_ref) {
                        if (strpos(strtolower($bsRow->payee_name), trim(strtolower($invoicePart)) ) !== false) {
                            $matchingInvoices[] = $invoiceNumber;
                        }
                    }
                }
            } else {
                if ($invoiceNumber) {
                    if (strpos(strtolower($bsRow->payment_ref), trim(strtolower($invoiceNumber)) ) !== false) {
                        $matchingInvoiceNumbers[] = $invoiceNumber;
                    } elseif ( !$bsRow->payment_ref ) {
                        if (strpos(strtolower($bsRow->payee_name), trim(strtolower($invoiceNumber)) ) !== false) {
                            $matchingInvoiceNumbers[] = $invoiceNumber;
                        }
                    }
                }
            }
        }
        // remove duplicate invoice number
        return array_keys(array_flip($matchingInvoiceNumbers));
    }

    private function getInvoicePart(string $invoiceNumber): ?string
    {
        $partial = preg_split('/\D[0]/', $invoiceNumber);

        return end($partial);
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

    private function exportUnmatchedStatementRows()
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $this->message = 'No Match Found';
            $this->exportRowsWithNoMatch($bsRow, null);
        }
    }

    private function exportRowsWithMatch(BankStatement $bsRow, OpenInvoice $openInvoiceRow)
    {
        $this->export[] = [
            $bsRow->sequence,
            $bsRow->transaction_date,
            $openInvoiceRow->customer_account,
            $openInvoiceRow->invoice_number,
            $bsRow->currency,
            $this->isPartialPayment ? $bsRow->original_amount : $openInvoiceRow->open_amount,
            $bsRow->payment_ref,
            $bsRow->payee_name,
            $openInvoiceRow->customer_name,
            $this->message,
            $bsRow->original_amount,
            $openInvoiceRow->open_amount,
            $this->isPartialPayment ? 'Yes' : 'No'
        ];

        $this->deleteElement($bsRow->sequence, $this->bsRowSequence);
        $this->deleteElement($openInvoiceRow->invoice_number, $this->invoiceNumbers);
        $this->message = '';
        $this->isPartialPayment = false;
    }

    private function exportRowsWithNoMatch(BankStatement $bsRow, float $difference=null, OpenInvoice $invoice=null)
    {
        $this->export[] = [
            $bsRow->sequence,
            $bsRow->transaction_date,
            $invoice ? $invoice->customer_account : '',
            '',
            $bsRow->currency,
            $difference ? $difference : $bsRow->original_amount,
            $bsRow->payment_ref,
            $bsRow->payee_name,
            $invoice ? $invoice->customer_name : '',
            $this->message,
            $bsRow->original_amount,
            '',
            $this->isPartialPayment ? 'Yes' : 'No'
        ];

        $this->deleteElement($bsRow->sequence, $this->bsRowSequence);

        $this->message = '';
        $this->isPartialPayment = false;
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
