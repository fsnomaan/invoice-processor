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
                    $openInvoiceTotal += (float) $openInvoiceRow->amount_transaction;
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
        if ( count($openInvoiceRows) == 1) {
            $note = 'total matched in single invoice.';
        } else {
            $note = 'total matched by multiple invoice.';
        }
        foreach($openInvoiceRows as $openInvoiceRow) {
            $note .= $bsRow->original_currency == $openInvoiceRow->currency ? '' : "\n Payment currency is different to invoice currency";
            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $note);
        }
    }

    private function exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal)
    {
        $differenceInTotal = $this->getDifferenceInTotal((float)$bsRow->original_amount, $openInvoiceTotal);
        if ($differenceInTotal < 0) {
            return;
        }

        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, 'unmatched invoice total');
        }

        $differenceInvoices = $this->openInvoice->getInvoiceFromTotalAndName((float)$differenceInTotal, $openInvoiceRows[0]->name);

        if ( $differenceInvoices->isNotEmpty() && count($differenceInvoices) == 1 ){
            $differenceInvoice = $differenceInvoices->first();
            $this->exportRowsWithMatch($bsRow, $differenceInvoice, 'matched invoice total by lookup');
        } else {
            $this->exportRowsWithDifference($bsRow, $differenceInTotal, 'difference in total. Please find invoice manually');
        }
    }

    private function exportRowsForMissingInvoices()
    {
        $unmatchedBsRows = $this->bs->getUnmatchedRows($this->matchedBsRows);

        foreach ($unmatchedBsRows as $unmatchedBsRow) {
            /** @var Collection $invoices */
            $invoices = $this->openInvoice->getInvoiceByAmount((float)$unmatchedBsRow['original_amount'], array_column($this->export, 3));

            if ($invoices->isEmpty()) {
                $this->exportRowsWithNoMatch($unmatchedBsRow);
            } else {
                if (count($invoices) == 1) {
                    $this->exportRowsWithMatch($unmatchedBsRow, $invoices->first(), 'Invoice matched based on total.');
                } else { // count is more than 1
                    $multipleInvoices = array_column($invoices->toArray(), 'invoice');
                    if (! empty($unmatchedBsRow->company_customer) ) {
                        /** @var Collection $invoiceRowByName */
                        $invoiceRowByName = $this->openInvoice->getInvoiceByMatchingName($unmatchedBsRow->company_customer, $multipleInvoices);
                        if ($invoiceRowByName->isNotEmpty()) {
                            $this->processRowsWithSimilarName($unmatchedBsRow, $invoiceRowByName, 'Invoice matched based on similar name.');
                        } else {
                            $this->exportRowsWithNoMatch($unmatchedBsRow);
                        }
                    }
                }
            }
        }
    }

    private function processRowsWithSimilarName($unmatchedBsRow, $invoiceRows, $note)
    {
        $invoiceRow = null;

        if (count($invoiceRows) > 1) {
            $found = $this->getRowsWithBSAmount((float)$unmatchedBsRow->original_amount, $invoiceRows);
            if ( count($found) == 1) {
                $invoiceRow = $found;
                $note .= $unmatchedBsRow->original_currency == $invoiceRow->currency ? '' : "\n Payment currency is different to invoice currency";
                $this->exportRowsWithMatch($unmatchedBsRow, $invoiceRow, $note);
            } else {
                $note = " Multiple Invoice matched based on similar name. \n Please manually select invoice.";
                $this->exportRowsWithMultipleMatch($unmatchedBsRow, $found, $note);
            }
        }
    }

    private function getRowsWithBSAmount(float $bsAmount, $invoiceRows)
    {
        $found = [];
        foreach ($invoiceRows as $row) {
            if ( (float)$row->amount_transaction == $bsAmount) {
                $found[] = $row;
            }
        }

        return $found;
    }

    private function exportRowsWithMatch($bsRow, $invoiceRow, $note)
    {
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
            $bsRow->original_amount,
            $bsRow->purpose_of_use

        ];
    }

    private function exportRowsWithNoMatch($unmatchedBsRow)
    {
            $this->export[] = [
                $unmatchedBsRow->trans_date,
                'not found',
                'not found',
                '',
                $unmatchedBsRow->original_amount,
                $unmatchedBsRow->original_currency,
                $unmatchedBsRow->company_customer,
                $unmatchedBsRow->trans_date,
                '01',
                'Missing invoice details',
                'not found',
                $unmatchedBsRow->original_amount,
                $unmatchedBsRow->purpose_of_use
            ];
    }

    private function exportRowsWithMultipleMatch($bsRow, $invoiceRows, $note)
    {
        $invoiceRow = $invoiceRows[0];

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
            $bsRow->original_amount,
            $bsRow->purpose_of_use
        ];
    }

    private function exportRowsWithDifference($bsRow, $difference, $note)
    {
        $this->export[] = [
            $bsRow->trans_date,
            'not found',
            'not found',
            '',
            round($difference, 2),
            $bsRow->original_currency,
            $bsRow->company_customer,
            $bsRow->trans_date,
            '01',
            $note,
            'not found',
            $bsRow->original_amount,
            $bsRow->purpose_of_use
        ];
    }

    private function streamResponse()
    {
        return new StreamedResponse(function(){
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'date',
                'customer',
                'invoice number',
                'debit',
                'credit',
                'currency',
                'payment reference',
                'document date',
                'trans type',
                'notes',
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
}
