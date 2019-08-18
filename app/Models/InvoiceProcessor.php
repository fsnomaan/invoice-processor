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

    private $isOverPayment = false;

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

        $this->invoiceNumbers = $this->openInvoice->getAllInvoiceNumbers($userId)->toArray();
        $this->bsRowSequence = $this->bs->getSequence($userId)->toArray();
        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $this->matchByInvoiceNumber($invoiceNumber);
        }

        $this->matchByPartialInvoiceNumber();

        $this->matchByInvoiceTotalOrName();

        $this->matchByAccountGrouping();

        $this->exportUnmatchedStatementRows();

//        dd($this->export);
        return $this->export;
    }

    private function matchByInvoiceNumber($invoiceNumber, bool $partial=false)
    {
        if ( empty($this->bsRowSequence) ) {
            return;
        }

        /** @var BankStatement[] $bsRows */
        $bsRows = $this->bs->getByInvoiceNumber($invoiceNumber, $this->userId);
        foreach ($bsRows as $bsRow) {
            if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $partial);
                // if only one match found
                if (count($matchingInvoiceNumbers) == 1 && !$partial) {
                    $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                    if ( (float)$openInvoiceRow->open_amount == (float)$bsRow->amount ) {
                        $this->message = 'Invoice Number';
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                    } elseif ( (float)$openInvoiceRow->open_amount < (float)$bsRow->amount ) {
                        $this->message = 'Invoice Number';
                        $this->isOverPayment = true;
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                    }
                    // if more than one match found
                } else {
                    /** @var OpenInvoice[] $openInvoiceRows */
                    $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoiceNumbers);
                    $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows);

                    if ( (float)$openInvoiceTotal == (float)$bsRow->amount )  {
                        foreach($openInvoiceRows as $openInvoiceRow) {
                            $this->message = 'Invoice Number';
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                        }
                    } elseif ( (float)$openInvoiceTotal > (float)$bsRow->amount )  {
                        // if all rows for same account, continue with partial payment
                        if ($this->isAllForSameAccount($openInvoiceRows)) {
                            foreach($openInvoiceRows as $openInvoiceRow) {
                                $this->message = empty($partial) ? 'Invoice Number' : 'Partial Invoice Number';
                                $this->isPartialPayment = true;
                                $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                            }
                        } else {
                            foreach($openInvoiceRows as $openInvoiceRow) {
                                if ($openInvoiceRow->open_amount == $bsRow->amount) {
                                    $this->message = 'Invoice Number';
                                    $this->exportRowsWithMatch($bsRow, $openInvoiceRow);
                                    break;
                                }
                            }
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
    }

    private function isAllForSameAccount(Collection $invoiceRows): bool
    {
        $accounts = array_column($invoiceRows->toArray(), 'customer_account');
        if (count(array_unique($accounts)) === 1 && end($accounts) === 'true') {
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
                $this->matchInvoiceByWord($bsRow, $invoice[0]);
            } elseif ( count($invoice) > 1 ) {
                if (!$this->matchByCompanyName($bsRow, $invoice)) {
                    $this->matchInvoicesByWords($bsRow, $invoice);
                }
            }
        }
    }

    private function matchInvoiceByWord(BankStatement $bsRow, OpenInvoice $invoice)
    {
        $needles = $this->getWordsByPosition($bsRow->payee_name);

        foreach (array_filter($needles) as $needle) {
            if (! $needle) {
                return;
            }

            $newNeedles = @unserialize($needle);
            if ($newNeedles == false) {
                $newNeedles[] = $needle;
            }

            foreach ($newNeedles as $newNeedle) {
                if (strpos(strtolower($invoice->customer_name), ' ' . strtolower($newNeedle) ) !== false ||
                    strpos(strtolower($invoice->customer_name), strtolower($newNeedle) . ' ' ) !== false
                ) {
                    $this->message = 'Payee Name';
                    $this->exportRowsWithMatch($bsRow, $invoice);
                    return;
                }
            }
        }
    }

    private function matchByCompanyName(BankStatement $bsRow, Collection $invoices): bool
    {
        $matchedInvoices = [];

        foreach ($invoices as $invoice) {
            $nameMap = $this->companyName->getByName($invoice->customer_name, $this->userId);
            if ($nameMap) {
                $nameMap = trim($nameMap);
                $message = 'Name Mapping';
            } else {
                $nameMap = $invoice->customer_name;
                $message = 'Customer Name';
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
                return true;
            }
        }

        return false;
    }

    private function matchInvoicesByWords(BankStatement $bsRow, Collection $invoices)
    {
        $needles = $this->getWordsByPosition($bsRow->payee_name);

        foreach (array_filter($needles) as $needle) {
            if (! $needle) {
                return;
            }
            $foundInvoices = [];
            foreach ($invoices as $invoice) {
                if (strpos(strtolower($invoice->customer_name), strtolower($needle)) !== false) {
                    $foundInvoices[] = $invoice;
                }
            }

            if ( count($foundInvoices) == 1) {
                $this->message = 'Payee Name';
                $this->exportRowsWithMatch($bsRow, $foundInvoices[0]);
                break;
            } elseif (count($foundInvoices) > 1) {
                $invoices = $foundInvoices;
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
                        $matchingInvoiceNumbers[] = $invoiceNumber;
                    } elseif (!$bsRow->payment_ref) {
                        if (strpos(strtolower($bsRow->payee_name), trim(strtolower($invoicePart)) ) !== false) {
                            $matchingInvoiceNumbers[] = $invoiceNumber;
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
        return array_unique($matchingInvoiceNumbers);
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

    private function exportUnmatchedStatementRows()
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $this->message = 'No Match Found';
            $this->exportRowsWithNoMatch($bsRow, null);
        }
    }

    private function matchByAccountGrouping()
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        /** @var BankStatement $bsRow */
        foreach ($bsRows as $bsRow) {
            $accountGroups = $this->openInvoice->getAccountGroupedByTotal($bsRow->original_amount);

            $needles = $this->getWordsByPosition($bsRow->payee_name);

            foreach (array_filter($needles) as $needle) {
                if (!$needle) {
                    return;
                }
                $foundInvoices = [];
                foreach ($accountGroups as $accountGroup) {
                    if (strpos(strtolower($accountGroup->customer_name), strtolower($needle)) !== false) {
                        $foundInvoices[] = $accountGroup;
                    }
                }

                if ( count($foundInvoices) == 1) {
                    $invoices = $this->openInvoice->getByCustomerAccount($foundInvoices[0]->customer_account);
                    foreach ($invoices as $invoice) {
                        $this->message = 'Account Total';
                        $this->exportRowsWithMatch($bsRow, $invoice);
                    }
                    break;
                } elseif (count($foundInvoices) > 1) {
                    $accountGroups = $foundInvoices;
                }
            }
        }
    }

    private function getWordByPosition(string $line, int $position = 0): string
    {
        $line = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $line)));
        $parts = explode(' ', trim($line) );
        if ( !isset($parts[$position]) ) {
            return '';
        }
        $words = explode(' ', trim($line) )[$position]; // 1ab&c
        preg_match('/\D+/', $words, $matches);
        if ($matches) {
            $match = end($matches); // ab&c
            $words = preg_replace('/[^A-Za-z]/', ' ', $match); // ab c
            $words = explode(' ', trim($words));

            $ampersandWords = [];
            if (count($words) > 1) {
                if (strpos($match, '&') !== FALSE) {
                    $ampersandWords[] = $words[0] . '&' . $words[1];
                    $ampersandWords[] = $words[0] . ' & ' . $words[1];
                    $ampersandWords[] = $words[0] . ' ' . $words[1];
                    $ampersandWords[] = $words[0] . '' . $words[1];
                    return serialize($ampersandWords);
                }
                return $words[0];
            }
            return $words[0];
        }
        return '';
    }

    private function getWordsByPosition(string $payeeName, int $count=5)
    {
        $needles = [];
        for($i=0; $i<$count; $i++) {
            $word = $this->getWordByPosition($payeeName, $i);
            if ( !in_array(trim(strtolower($word)), ['the', 'uk', 'ltd', 'limited']) ) {
                $needles[] = $word;
            }
        }

        return $needles;
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
            $this->isPartialPayment ? 'Yes' : 'No',
            $this->isOverPayment ? 'Yes' : 'No'
        ];

        $this->deleteElement($bsRow->sequence, $this->bsRowSequence);

        if (!$this->isPartialPayment) {
            $this->deleteElement($openInvoiceRow->invoice_number, $this->invoiceNumbers);
        }

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
            'No',
            'No'
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
