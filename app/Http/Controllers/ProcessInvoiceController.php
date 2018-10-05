<?php

namespace App\Http\Controllers;

use App\Models\BankStatement;
use App\Models\OpenInvoice;
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
                // - get difference in total
                // - add to export array with the paid invoice
                // - add to export array with the unpaid difference
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
                $bsRow->purpose_of_use,
                $openInvoiceRow->invoice,
                '',
                $openInvoiceRow->amount_transaction,
                $bsRow->original_currency,
                $bsRow->company_customer,
                $bsRow->trans_date,
                '01',
                'total matched'
            ];
        }
    }

    private function exportRowsForUnmatchedTotal($bsRow, $openInvoiceRows, $openInvoiceTotal)
    {
        foreach($openInvoiceRows as $openInvoiceRow) {
            $this->export[] = [
                $bsRow->trans_date,
                $openInvoiceRow->customer_account,
                $bsRow->purpose_of_use,
                $openInvoiceRow->invoice,
                '',
                $openInvoiceRow->amount_transaction,
                $bsRow->original_currency,
                $bsRow->company_customer,
                $bsRow->trans_date,
                '01',
                'unmatched total'
            ];
        }

        $differenceInTotal = $this->getDifferenceInTotal($bsRow->original_amount, $openInvoiceTotal);
        try {
            $this->export[] = [
                $bsRow->trans_date,
                $openInvoiceRows[0]->customer_account,
                $bsRow->purpose_of_use,
                $openInvoiceRows[0]->invoice,
                '',
                $differenceInTotal,
                $bsRow->original_currency,
                $bsRow->company_customer,
                $bsRow->trans_date,
                '01',
                'difference in total'
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
            $this->export[] = [
                $unmatchedBsRow->trans_date,
                'no account found',
                $unmatchedBsRow->purpose_of_use,
                'no matching invoice',
                '',
                $unmatchedBsRow->original_amount,
                $unmatchedBsRow->original_currency,
                $unmatchedBsRow->company_customer,
                $unmatchedBsRow->trans_date,
                '01',
                'Missing invoice details'
            ];
        }
    }

    private function streamResponse()
    {
        return new StreamedResponse(function(){
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'date',
                'customer',
                'purpose of use',
                'invoice number',
                'debit',
                'credit',
                'currency',
                'payment reference',
                'document date',
                'trans type',
                'notes'
            ]);

            foreach ($this->export as $row) {
                fputcsv($handle, $row);
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
