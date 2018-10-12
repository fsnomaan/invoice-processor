<?php

namespace App\Http\Controllers;

use App\Models\BankStatement;
use App\Models\OpenInvoice;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcessInvoiceController extends Controller
{
    /** @var BankStatement */
    private $bs;

    /** @var OpenInvoice */
    private $openInvoice;

    /** @var array  */
    private $export = [];

    private $matchedBsRows = [];

    public function __construct(BankStatement $bs, OpenInvoice $openInvoice)
    {
        $this->bs = $bs;
        $this->openInvoice = $openInvoice;
        $this->export = [];
    }

    public function index()
    {
        $response = [
            'success' => ''
        ];
        return view('process_invoice')->with($response);
    }

    public function processInvoice()
    {
        $invoices = $this->openInvoice->getAllInvoices()->toArray();
        foreach ($invoices as $key => $invoice) {
            $this->createExportRow($invoice, $invoices);
        }

        $this->exportRowsForMissingInvoices();

        return $this->streamResponse();
    }

    private function createExportRow($invoiceNumber, &$invoices)
    {
        if (! in_array($invoiceNumber, $invoices) ) {
            return;
        }

        $matchingInvoice = [];
        $bsRow = $this->bs->getRowsLikeInvoice($invoiceNumber);
        if (! empty($bsRow) ) {
            $this->matchedBsRows[] = $bsRow->id;

            foreach ($invoices as $key => $invoice) {
                if (! empty($invoice)) {
                    if (strpos($bsRow->purpose_of_use, trim($invoice) ) !== false) {

                        $matchingInvoice[] = $invoice;
                        unset($invoices[$key]);
                    }
                }
            }

            $openInvoiceTotal = 0;
            $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoice);
            foreach($openInvoiceRows as $openInvoiceRow) {
                try {
                    $openInvoiceTotal += (float) $this->removeComma($openInvoiceRow->amount_transaction);
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
            }

            if ( $this->isTotalMatches((float) $bsRow->original_amount, (float) $openInvoiceTotal)) {
                $this->exportRowsForMatchingTotal($bsRow, $openInvoiceRows);
            } else {
                $this->exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal);
            }
        }
    }

    private function exportRowsForMatchingTotal($bsRow, $openInvoiceRows)
    {
        $note = 'Matched';

        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $note);
        }
    }

    private function exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal)
    {
        $differenceInTotal = $this->getDifferenceInTotal((float) $this->removeComma($bsRow->original_amount), $openInvoiceTotal);
        if ($differenceInTotal < 0 ) {
            return;
        }

        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, 'Unmatched total payment');
        }

        $differenceInvoices = $this->openInvoice->getInvoiceFromTotalAndName((float)$differenceInTotal, $openInvoiceRows[0]->name);

        if ( $differenceInvoices->isNotEmpty() && count($differenceInvoices) == 1 ){
            $differenceInvoice = $differenceInvoices->first();
            $this->exportRowsWithMatch($bsRow, $differenceInvoice, 'Matched');
        } else {
            if ($differenceInTotal > 0 ) {
                $this->exportRowsWithDifference($bsRow, $differenceInTotal, 'Difference in total. Please find invoice manually');
            }
        }
    }

    private function exportRowsForMissingInvoices()
    {
        $unmatchedBsRows = $this->bs->getUnmatchedRows($this->matchedBsRows);

        foreach ($unmatchedBsRows as $unmatchedBsRow) {
            /** @var Collection $invoices */
            $invoices = $this->openInvoice->getInvoiceByAmount((float)$unmatchedBsRow['original_amount'], array_column($this->export, 3));

            if (count($invoices) > 1 ) {
                $multipleInvoices = array_column($invoices->toArray(), 'invoice');

                /** @var Collection $invoiceRowByName */
                $invoiceRowByName = $this->openInvoice->getInvoiceByMatchingName($unmatchedBsRow->company_customer, $multipleInvoices);
                if ($invoiceRowByName->isNotEmpty()) {
                    $this->processRowsWithSimilarName($unmatchedBsRow, $invoiceRowByName, 'Invoice matched based on similar name.');
                } else {
                    $this->exportRowsWithNoMatch($unmatchedBsRow);
                }
            } elseif (count($invoices) == 1 ) {
                $this->exportRowsWithMatch($unmatchedBsRow, $invoices->first(), 'Invoice matched based on total.');
            } else {
                $this->exportRowsWithNoMatch($unmatchedBsRow);

            }
        }
    }

    private function processRowsWithSimilarName($unmatchedBsRow, $invoiceRows, $note)
    {
        $invoiceRow = null;

        if (count($invoiceRows) > 1) {
            $found = $this->getRowsWithBSAmount((float)$this->removeComma($unmatchedBsRow->original_amount), $invoiceRows);

            if ( count($found) == 1) {
                $invoiceRow = $found;
                $this->exportRowsWithMatch($unmatchedBsRow, $invoiceRow, $note);
            } else {
                $note = "Multiple Invoice matched based on similar name. \nPlease manually select invoice.";
                $this->exportRowsWithMultipleMatch($unmatchedBsRow, $found, $note);
            }
        } elseif (count($invoiceRows) == 1) {
            $this->exportRowsWithMatch($unmatchedBsRow, $invoiceRows->first(), $note);
        }
    }

    private function getRowsWithBSAmount(float $bsAmount, $invoiceRows)
    {
        $found = [];
        foreach ($invoiceRows as $row) {
            if ( (float)$this->removeComma($row->amount_transaction) == $bsAmount) {
                $found[] = $row;
            }
        }

        return $found;
    }

    private function exportRowsWithMatch($bsRow, $invoiceRow, $note)
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
    }

    private function exportRowsWithNoMatch($unmatchedBsRow)
    {
            $this->export[] = [
                $unmatchedBsRow->trans_date,
                'Not found',
                'Not found',
                '',
                $unmatchedBsRow->amount,
                $unmatchedBsRow->original_currency,
                $unmatchedBsRow->company_customer,
                $unmatchedBsRow->trans_date,
                '01',
                'Missing invoice details',
                'Not found',
                $unmatchedBsRow->amount,
                $unmatchedBsRow->purpose_of_use
            ];
    }

    private function exportRowsWithMultipleMatch($bsRow, $invoiceRows, $note)
    {
        $invoiceRow = $invoiceRows[0];
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

    private function exportRowsWithDifference($bsRow, $difference, $note)
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

    private function streamResponse()
    {
        return new StreamedResponse(function(){
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Customer',
                'Invoice number',
                'Debit',
                'Credit',
                'Currency',
                'Payment reference',
                'Document date',
                'Trans type',
                'Notes',
                'OPOS File Customer Name',
                'bank statement total',
                'Bank Statement invoices'
            ], ';');

            foreach ($this->export as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="export_'.date("Ymd_Hi").'.csv"',
        ]);
    }

    private function isTotalMatches(float $bsTotal, float $openInvoiceTotal)
    {
        return $bsTotal == $openInvoiceTotal;
    }

    private function getDifferenceInTotal($bsTotal, $openInvoiceTotal) {
        return $bsTotal - $openInvoiceTotal;
    }

    private function removeComma($value)
    {
        return str_replace(',', '', $value);
    }
}
