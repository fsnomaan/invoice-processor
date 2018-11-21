<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

class InvoiceProcessor
{
    /** @var BankStatement */
    private $bs;

    /** @var OpenInvoice */
    private $openInvoice;

    private $invoices = [];

    /** @var array  */
    private $export = [];

    /** @var array  */
    private $matchedBsRows = [];

    /** @var CompanyName */
    private $companyName;

    /** @var BankAccount */
    private $bankAccount;

    /** @var  array */
    private $bankAccountMap;

    /** @var string  */
    private $bsAmountNote = "";

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

    public function processInvoice() :array
    {
        $this->bankAccountMap = $this->bankAccount->getAccounts();

        $this->invoices = $this->openInvoice->getAllInvoices()->toArray();
        foreach ($this->invoices as $key => $invoice) {
            $this->createExportRow($invoice, $this->invoices);
        }

        foreach ($this->invoices as $key => $invoice) {
            $this->createExportRowWithPartialInvoice($invoice, $this->invoices);
        }
        $this->exportRowsForMissingInvoices();

        return $this->export;
    }

    private function createExportRow(string $invoiceNumber, array &$invoices)
    {
        if (! in_array($invoiceNumber, $invoices) ) {
            return;
        }

        $bsRow = $this->bs->getRowsLikeInvoice($invoiceNumber);

        if (! empty($bsRow) ) {
            $matchingInvoices = $this->getMatchingInvoices($bsRow, $invoices);
            $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoices);
            $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows);

            if ( $this->isTotalMatches($this->getBsAmount($bsRow), (float) $openInvoiceTotal)) {
                $this->exportRowsForMatchingTotal($bsRow, $openInvoiceRows, $this->getNote("Matched"));
            } else {
                $this->exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal, 'Unmatched total payment');
            }
        }
    }

    private function getMatchingInvoices(BankStatement $bsRow, array &$invoices) : array
    {
        $matchingInvoices = [];

        foreach ($invoices as $key => $invoice) {

            if (strpos($bsRow->purpose_of_use, trim($invoice) ) !== false) {
                $matchingInvoices[] = $invoice;
            }
        }

        return $matchingInvoices;
    }

    private function getOpenInvoicesTotal(Collection $openInvoiceRows) : float
    {
        $openInvoiceTotal = 0;
        foreach($openInvoiceRows as $openInvoiceRow) {
            $openInvoiceTotal += (float) $openInvoiceRow->amount_transaction;
        }

        return $openInvoiceTotal;
    }

    private function exportRowsForMatchingTotal(BankStatement $bsRow, Collection $openInvoiceRows, string $note='')
    {
        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $note);
        }
    }

    private function exportRowsForUnmatchedTotal(BankStatement $bsRow, Collection $openInvoiceRows, float $openInvoiceTotal, string $note='')
    {
        $differenceInTotal = $this->getDifferenceInTotal($this->getBsAmount($bsRow), $openInvoiceTotal);
        $differenceInvoices = $this->openInvoice->getInvoiceFromTotalAndName((float)$differenceInTotal, $openInvoiceRows[0]->name);

        if ($differenceInTotal == 0 ) {
            $note = 'Matched';
        } elseif ( $differenceInvoices->isNotEmpty() && count($differenceInvoices) == 1 ) {
            $note = 'Matched';
        }

        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getNote($note));
        }

        if ( $differenceInvoices->isNotEmpty() && count($differenceInvoices) == 1 ){
            $differenceInvoice = $differenceInvoices->first();
            $this->exportRowsWithMatch($bsRow, $differenceInvoice, "Matched");
        } else {
            if ( $differenceInTotal == 0 ) { return; }
            $this->exportRowsWithDifference($bsRow, $differenceInTotal, 'Please find invoice manually');
        }
    }

    private function createExportRowWithPartialInvoice(string $invoiceNumber, array &$invoices)
    {
        if (! in_array($invoiceNumber, $invoices) ) {
            return;
        }

        $parts = explode('-', $invoiceNumber);

        if ( isset($parts[1])) {
            $bsRow = $this->bs->getRowsLikeInvoice($parts[1]);

            if (! empty($bsRow)) {
                $note = 'Matched by partial invoice number';
                $matchingInvoices = $this->getPartialMatchingInvoices($bsRow, $invoices);
                $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoices);
                $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows);

                if ( $this->isTotalMatches($this->getBsAmount($bsRow), (float) $openInvoiceTotal)) {
                    foreach($openInvoiceRows as $openInvoiceRow) {
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getNote($note));
                    }
                }
            }
        }
    }

    private function getPartialMatchingInvoices(BankStatement $bsRow, array &$invoices) : array
    {
        $matchingInvoices = [];

        foreach ($invoices as $key => $invoice) {
            if (! empty($invoice)) {
                $parts = explode('-', $invoice);

                if ( isset($parts[1])) {
                    $needle = $parts[1];
                    if (strpos($bsRow->purpose_of_use, trim($needle) ) !== false) {
                        $matchingInvoices[] = $invoice;
                        unset($invoices[$key]);
                    }
                }

            }
        }

        return $matchingInvoices;
    }

    private function exportRowsForMissingInvoices()
    {
        $unmatchedBsRows = $this->bs->getUnmatchedRows($this->matchedBsRows);

        foreach ($unmatchedBsRows as $unmatchedBsRow) {
            /** @var Collection $invoices */

            $amount = $this->getBsAmount($unmatchedBsRow);
            $invoices = $this->openInvoice->getInvoiceByAmount($amount, array_column($this->export, 3));

            if (count($invoices) == 1 ) {
                $invoice = $invoices->first();
                if ($amount == $invoice->amount_transaction) {
                    $this->exportRowsWithMatch($unmatchedBsRow, $invoice, 'Invoice matched based on total.');
                } else {
                    $this->exportRowsWithDifference($unmatchedBsRow, ($amount - (float)$invoice->amount_transaction), 'Please find invoice manually');
                }

            } elseif (count($invoices) > 1 ) {
                $multipleInvoices = array_column($invoices->toArray(), 'invoice');

                /** @var Collection $invoiceRowByName */
                $invoiceRowByName = $this->openInvoice->getInvoiceByMatchingName(
                    $this->getCompanyCustomerName($unmatchedBsRow->company_customer),
                    $multipleInvoices
                );
                if ($invoiceRowByName->isNotEmpty()) {
                    $this->processRowsWithSimilarName($unmatchedBsRow, $invoiceRowByName, 'Invoice matched based on similar name.');
                } else {
                    $this->exportRowsWithNoMatch($unmatchedBsRow, 'Multiple invoice found');
                }
            } else {
                $this->exportRowsWithNoMatch($unmatchedBsRow);
            }
        }
    }

    private function processRowsWithSimilarName(BankStatement $unmatchedBsRow, Collection $invoiceRows, $note)
    {
        $invoiceRow = null;

        $amount = $this->getBsAmount($unmatchedBsRow);

        if (count($invoiceRows) > 1) {
            $found = $this->getRowsWithBSAmount($amount, $invoiceRows);

            if ( count($found) == 1) {
                /** @var OpenInvoice $invoiceRow */
                $invoiceRow = $found[0];
                $this->exportRowsWithMatch($unmatchedBsRow, $invoiceRow, $note);
            } elseif ( count($found) > 1 ) {
                $note = "Multiple Invoice matched based on similar name. \nPlease manually select invoice.";
                /** @var OpenInvoice $invoiceRow */
                $invoiceRow = $found[0];
                $this->exportRowsWithMultipleMatch($unmatchedBsRow, $invoiceRow, $note);
            } else {
                $this->exportRowsWithNoMatch($unmatchedBsRow);
            }
        } elseif (count($invoiceRows) == 1) {
            $openInvoiceRow = $invoiceRows->first();
            if ( $this->isTotalMatches(
                $amount,
                (float)$openInvoiceRow->amount_transaction)
            ) {
                $this->exportRowsWithMatch($unmatchedBsRow, $openInvoiceRow, $note);
            } else {
                $this->exportRowsWithNoMatch($unmatchedBsRow);
            }
        }
    }

    private function getRowsWithBSAmount(float $bsAmount, Collection $invoiceRows) : array
    {
        $found = [];
        foreach ($invoiceRows as $row) {
            if ( (float)$row->amount_transaction == $bsAmount) {
                $found[] = $row;
            }
        }

        return $found;
    }

    private function exportRowsWithMatch(BankStatement $bsRow, OpenInvoice $invoiceRow, string $note)
    {
        $this->matchedBsRows[] = $bsRow->id;

        $note .= $bsRow->currency == $bsRow->original_currency ? '' : "\nDifferent Currency";
        $note .= $invoiceRow->currency == $bsRow->original_currency ? '' : "\nDifference in invoice currency";

        $currency = empty($bsRow->original_currency) ? $bsRow->currency : $bsRow->original_currency;

        $this->export[] = [
            $bsRow->trans_date,
            $invoiceRow->customer_account,
            $invoiceRow->invoice,
            '',
            $invoiceRow->amount_transaction,
            $currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            $this->getBankAccountId((int)$bsRow->datev_account_number),
            $note,
            $invoiceRow->name,
            $bsRow->amount,
            $bsRow->purpose_of_use

        ];
        $this->deleteElement($invoiceRow->invoice, $this->invoices);
    }

    private function exportRowsWithNoMatch(BankStatement $bsRow, $note="Missing invoice details")
    {
        $currency = empty($bsRow->original_currency) ? $bsRow->currency : $bsRow->original_currency;

        $this->matchedBsRows[] = $bsRow->id;

        $this->export[] = [
            $bsRow->trans_date,
            'Not found',
            'Not found',
            '',
            $bsRow->amount,
            $currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            $this->getBankAccountId((int)$bsRow->datev_account_number),
            $note,
            'Not found',
            $bsRow->amount,
            $bsRow->purpose_of_use
        ];
    }

    private function exportRowsWithMultipleMatch(BankStatement $bsRow, OpenInvoice $invoiceRow, $note)
    {
        $this->matchedBsRows[] = $bsRow->id;

        $note .= $bsRow->currency == $bsRow->original_currency ? '' : "\n Different Currency";
        $note .= $invoiceRow->currency == $bsRow->original_currency ? '' : "\nDifference in invoice currency";

        $currency = empty($bsRow->original_currency) ? $bsRow->currency : $bsRow->original_currency;

        $this->export[] = [
            $bsRow->trans_date,
            $invoiceRow->customer_account,
            '',
            '',
            $invoiceRow->amount_transaction,
            $currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            $this->getBankAccountId((int)$bsRow->datev_account_number),
            $note,
            $invoiceRow->name,
            $bsRow->amount,
            $bsRow->purpose_of_use
        ];
    }

    private function exportRowsWithDifference(BankStatement $bsRow, float $difference, string $note)
    {
        $currency = empty($bsRow->original_currency) ? $bsRow->currency : $bsRow->original_currency;

        $this->matchedBsRows[] = $bsRow->id;

        $this->export[] = [
            $bsRow->trans_date,
            'Not found',
            'Not found',
            '',
            round($difference, 2),
            $currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            $this->getBankAccountId((int)$bsRow->datev_account_number),
            $note,
            'Not found',
            $bsRow->amount,
            $bsRow->purpose_of_use
        ];
    }

    private function isTotalMatches(float $bsTotal, float $openInvoiceTotal) : bool
    {
        return $bsTotal == $openInvoiceTotal;
    }

    private function getDifferenceInTotal(float $bsTotal, float $openInvoiceTotal) : float
    {
        return round(($bsTotal - $openInvoiceTotal), 2);
    }

    private function getCompanyCustomerName(string $name) : string
    {
        $map = $this->companyName->getNames();

        if (array_key_exists($name, $map) ) {
            return $map[$name];
        }

        return $name;
    }

    private function deleteElement($element, &$array){
        $index = array_search($element, $array);
        if($index !== false){
            unset($array[$index]);
        }
    }

    private function getBsAmount(BankStatement $bsRow)
    {
        if ( $bsRow->currency == $bsRow->original_currency ) {
            if ( $bsRow->amount != $bsRow->original_amount ) {
                $this->bsAmountNote = '\nBank charge adjusted';
                return (float)$bsRow->amount;
            }
        }

        if ( empty($bsRow->original_amount) ) {
            return (float)$bsRow->amount;
        }

        return (float)$bsRow->original_amount;
    }

    private function getBankAccountId(int $accountNumber)
    {
        if ( isset($this->bankAccountMap[$accountNumber])) {
            return $this->bankAccountMap[$accountNumber];
        }

        return $accountNumber;
    }

    private function getNote(string $note)
    {
        if ( !empty($this->bsAmountNote) ) {
            $note .= $this->bsAmountNote;
        }

        return $note;
    }
}
