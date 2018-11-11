<?php

namespace App\Http\Controllers;

use App\Models\InvoiceImporter;
use App\Models\InvoiceProcessor;
use App\Models\StatementImporter;
use Illuminate\Http\Request;
use App\Models\CompanyName;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcessInvoiceController extends Controller
{
    /** @var StatementImporter */
    private $statementImporter;

    /** @var InvoiceImporter */
    private $invoiceImporter;

    /** @var CompanyName */
    private $companyName;

    /** @var string  */
    private $separator = ';';

    /** @var string */
    private $invoicePrimary;

    /** @var InvoiceProcessor */
    private $invoiceProcessor;

    /** @var array  */
    private $export = [];

    public function __construct(
        StatementImporter $statementImporter,
        InvoiceImporter $invoiceImporter,
        CompanyName $companyName,
        InvoiceProcessor $invoiceProcessor
    ) {
        $this->statementImporter = $statementImporter;
        $this->invoiceImporter = $invoiceImporter;
        $this->companyName = $companyName;
        $this->invoiceProcessor = $invoiceProcessor;
    }

    public function index(Request $request)
    {
        $response = [
            'companyNames' => $this->companyName->getNames(),
            'success' => $request->message
        ];

        return view('process_invoice')->with($response);
    }

    public function processInvoice(Request $request)
    {

        $this->validateForm($request);

        $this->invoicePrimary = $request->invoiceFirstPart;
        $this->separator = empty($request->separator) ? $this->separator : $request->separator;

        if ($request->hasFile('bankStatement') && $request->file('bankStatement')->isValid()) {
            $file = $request->file('bankStatement');
            $path = $file->getRealPath();
            $this->statementImporter->setSeparator($this->separator);
            $this->statementImporter->setInvoicePrimary($this->invoicePrimary);
            $this->statementImporter->importBankStatement($path);
        }

        if ($request->hasFile('openInvoice') && $request->file('openInvoice')->isValid()) {
            $file = $request->file('openInvoice');
            $path = $file->getRealPath();
            $this->invoiceImporter->setSeparator($this->separator);
            $this->invoiceImporter->importOpenInvoice($path);
        }

        $this->export = $this->invoiceProcessor->processInvoice();

        return $this->streamResponse();
    }


    private function validateForm($request)
    {
        $rules = [
            'invoiceFirstPart' => '
                required',
            'bankStatement' => '
                required
                |
                mimetypes:text/plain,text/x-Algol68,application/csv,application/excel,application/vnd.ms-excel,application/vnd.msexcel,text/csv,text/anytext,text/comma-separated-values',
            'openInvoice' => '
                required
                |
                mimetypes:text/plain,text/x-Algol68,application/csv,application/excel,application/vnd.ms-excel,application/vnd.msexcel,text/csv,text/anytext,text/comma-separated-values',
        ];

        $customMessages = [
            'required' => 'The :attribute field is required.',
            'mimetypes' => ':attribute not a valid csv file'
        ];

        $this->validate($request, $rules, $customMessages);
    }

    private function streamResponse()
    {
        $columnHeadings = [
            'Date',
            'Customer',
            'Invoice number',
            'Debit',
            'Credit',
            'Currency',
            'Bank Statement Name',
            'Document date',
            'Trans type',
            'Notes',
            'OPOS File Customer Name',
            'bank statement total',
            'Bank Statement invoices'
        ];
        $sortedExport = [];
        foreach ($this->export as $row) {
            $sortedExport[] = array_combine($columnHeadings, $row);
        }

        usort($sortedExport, function($a, $b) {
            return $a['bank statement total'] <=> $b['bank statement total'];
        });

        return new StreamedResponse(function() use ($columnHeadings, $sortedExport){
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $columnHeadings, ';');

            foreach ($sortedExport as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="export_'.date("Ymd_Hi").'.csv"',
        ]);
    }
}
