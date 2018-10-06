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
                    $openInvoiceTotal += $openInvoiceRow->amount_transaction;
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
            }

            if ( $this->isTotalMatches($bsRow->original_amount, $openInvoiceTotal)) {
                $this->exportRowsForMatchingTotal($bsRow, $openInvoiceRows);
            } else {
                $this->exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal);
            }
        }
    }

    private function exportRowsForMatchingTotal($bsRow, $openInvoiceRows)
    {
        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->export[] = [
                $bsRow->trans_date,
                $openInvoiceRow->customer_account,
                $openInvoiceRow->invoice,
                '',
                $openInvoiceRow->amount_transaction,
                $bsRow->original_currency,
                $bsRow->company_customer,
                $bsRow->trans_date,
                '01',
                'total matched',
                $openInvoiceRow->name,
                $bsRow->account_holder

            ];
        }
    }

    private function exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal)
    {
        $differenceInTotal = $this->getDifferenceInTotal($bsRow->original_amount, $openInvoiceTotal);
        if ($differenceInTotal < 0) {
            return;
        }

        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->export[] = [
                $bsRow->trans_date,
                $openInvoiceRow->customer_account,
                $openInvoiceRow->invoice,
                '',
                $openInvoiceRow->amount_transaction,
                $bsRow->original_currency,
                $bsRow->company_customer,
                $bsRow->trans_date,
                '01',
                'unmatched total',
                $openInvoiceRow->name,
                $bsRow->account_holder
            ];
        }

        try {
            $this->export[] = [
                $bsRow->trans_date,
                $openInvoiceRows[0]->customer_account,
                $openInvoiceRows[0]->invoice,
                '',
                $differenceInTotal,
                $bsRow->original_currency,
                $bsRow->company_customer,
                $bsRow->trans_date,
                '01',
                'difference in total',
                $openInvoiceRows[0]->name,
                $bsRow->account_holder
            ];
        } catch (\Exception $e) {
            var_dump($bsRow->original_amount);
            var_dump($openInvoiceTotal);
            var_dump($bsRow->toArray());
            dd($openInvoiceRows);
        }
    }

    private function exportRowsForMissingInvoices()
    {
        $unmatchedBsRows = $this->bs->getUnmatchedRows($this->matchedBsRows);

        foreach ($unmatchedBsRows as $unmatchedBsRow) {
            /** @var Collection $invoices */
            $invoices = $this->openInvoice->getInvoiceByAmount($unmatchedBsRow['original_amount'], array_column($this->export, 3));

            if ($invoices->isEmpty()) {
                $this->exportRowsWithNoMatch($unmatchedBsRow);
            } else {
                if (count($invoices) == 1) {
                    $invoiceRow = $invoices->first();
                    $this->exportRowsWithOneMatch($unmatchedBsRow, $invoiceRow, 'Invoice matched based on total');
                } else { // count is more than 1
                    $multipleInvoices = array_column($invoices->toArray(), 'invoice');
                    if (! empty($unmatchedBsRow->company_customer) ) {
                        /** @var Collection $invoiceRowByName */
                        $invoiceRowByName = $this->openInvoice->getInvoiceByMatchingName($unmatchedBsRow->company_customer, $multipleInvoices);
                        if ($invoiceRowByName->isNotEmpty()) {
                            $this->exportRowsWithOneMatch($unmatchedBsRow, $invoiceRowByName->first(), 'Invoice matched based on similar name');
                        } else {
                            $this->exportRowsWithNoMatch($unmatchedBsRow);
                        }
                    }
                }
            }
        }
    }

    private function exportRowsWithOneMatch($unmatchedBsRow, $invoiceRow, $note)
    {
        $this->export[] = [
            $unmatchedBsRow->trans_date,
            $invoiceRow->customer_account,
            $invoiceRow->invoice,
            '',
            $invoiceRow->amount_transaction,
            $unmatchedBsRow->original_currency,
            $unmatchedBsRow->company_customer,
            $unmatchedBsRow->trans_date,
            '01',
            $note,
            $invoiceRow->name,
            $unmatchedBsRow->account_holder
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
                $unmatchedBsRow->account_holder
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
                'purpose of use'
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

    private function isTotalMatches($bsTotal, $openInvoiceTotal)
    {
        return $bsTotal == $openInvoiceTotal;
    }

    private function getDifferenceInTotal($bsTotal, $openInvoiceTotal) {
        return $bsTotal - $openInvoiceTotal;
    }
}
