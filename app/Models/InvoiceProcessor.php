<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function __construct(
        BankStatement $bs,
        OpenInvoice $openInvoice,
        CompanyName $companyName
    ) {
        $this->export = [];
        $this->bs = $bs;
        $this->openInvoice = $openInvoice;
        $this->companyName = $companyName;
    }

    public function processInvoice() :array
    {
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

            if ( $this->isTotalMatches((float) $bsRow->original_amount, (float) $openInvoiceTotal)) {
                $this->exportRowsForMatchingTotal($bsRow, $openInvoiceRows, 'Matched');
            } else {
                $this->exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal, 'Unmatched total payment');
            }
        }
    }

    private function getMatchingInvoices(BankStatement $bsRow, array &$invoices) : array
    {
        $matchingInvoices = [];
        $this->matchedBsRows[] = $bsRow->id;

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
            $openInvoiceTotal += (float) $this->removeComma($openInvoiceRow->amount_transaction);
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
        $differenceInTotal = $this->getDifferenceInTotal((float) $this->removeComma($bsRow->original_amount), $openInvoiceTotal);
        $differenceInvoices = $this->openInvoice->getInvoiceFromTotalAndName((float)$differenceInTotal, $openInvoiceRows[0]->name);

        if ($differenceInTotal == 0 ) {
            $note = 'Matched';
        } elseif ( $differenceInvoices->isNotEmpty() && count($differenceInvoices) == 1 ) {
            $note = 'Matched';
        }

        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $note);
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

                if ( $this->isTotalMatches((float) $bsRow->original_amount, (float) $openInvoiceTotal)) {
                    foreach($openInvoiceRows as $openInvoiceRow) {
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $note);
                    }
                }
            }
        }
    }

    private function getPartialMatchingInvoices(BankStatement $bsRow, array &$invoices) : array
    {
        $matchingInvoices = [];
        $this->matchedBsRows[] = $bsRow->id;

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

            $invoices = $this->openInvoice->getInvoiceByAmount((float)$unmatchedBsRow['original_amount'], array_column($this->export, 3));

            if (count($invoices) == 1 ) {
                $this->exportRowsWithMatch($unmatchedBsRow, $invoices->first(), 'Invoice matched based on total.');
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

        if (count($invoiceRows) > 1) {
            $found = $this->getRowsWithBSAmount((float)$this->removeComma($unmatchedBsRow->original_amount), $invoiceRows);

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
                (float)$this->removeComma($unmatchedBsRow->original_amount),
                (float)$this->removeComma($openInvoiceRow->amount_transaction))
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
            if ( (float)$this->removeComma($row->amount_transaction) == $bsAmount) {
                $found[] = $row;
            }
        }

        return $found;
    }

    private function exportRowsWithMatch(BankStatement $bsRow, OpenInvoice $invoiceRow, string $note)
    {
        $note .= $bsRow->currency == $bsRow->original_currency ? '' : "\nDifferent Currency";

        $this->export[] = [
            $bsRow->trans_date,
            $invoiceRow->customer_account,
            $invoiceRow->invoice,
            '',
            $invoiceRow->amount_transaction,
            $bsRow->original_currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            '01',
            $note,
            $invoiceRow->name,
            $bsRow->amount,
            $bsRow->purpose_of_use

        ];
        $this->deleteElement($invoiceRow->invoice, $this->invoices);
    }

    private function exportRowsWithNoMatch(BankStatement $unmatchedBsRow, $note="Missing invoice details")
    {
        $this->export[] = [
            $unmatchedBsRow->trans_date,
            'Not found',
            'Not found',
            '',
            'Not found',
            $unmatchedBsRow->original_currency,
            $unmatchedBsRow->company_customer,
            $unmatchedBsRow->trans_date,
            '01',
            $note,
            'Not found',
            $unmatchedBsRow->amount,
            $unmatchedBsRow->purpose_of_use
        ];
    }

    private function exportRowsWithMultipleMatch(BankStatement $bsRow, OpenInvoice $invoiceRow, $note)
    {
        $note .= $bsRow->currency == $bsRow->original_currency ? '' : "\n Different Currency";

        $this->export[] = [
            $bsRow->trans_date,
            $invoiceRow->customer_account,
            '',
            '',
            $invoiceRow->amount_transaction,
            $bsRow->original_currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            '01',
            $note,
            $invoiceRow->name,
            $bsRow->amount,
            $bsRow->purpose_of_use
        ];
    }

    private function exportRowsWithDifference(BankStatement $bsRow, float $difference, string $note)
    {
        $this->export[] = [
            $bsRow->trans_date,
            'Not found',
            'Not found',
            '',
            round($difference, 2),
            $bsRow->original_currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            '01',
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

    private function removeComma(string $value) : string
    {
        return str_replace(',', '', $value);
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
}
