<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Importer\InvoiceImporter;
use App\Models\InvoiceProcessor;
use App\Models\Importer\StatementImporter;
use App\Models\XmlStatementImporter;
use Illuminate\Http\Request;
use App\Models\CompanyName;
use App\User;
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
    private $separator = ',';

    /** @var InvoiceProcessor */
    private $invoiceProcessor;

    /** @var array  */
    private $export = [];

    /** @var BankAccount */
    private $bankAccount;

    /** @var User */
    private $user;
    /**
     * @var XmlStatementImporter
     */
    private $xmlStatementImporter;

    public function __construct(
        StatementImporter $statementImporter,
        XmlStatementImporter $xmlStatementImporter,
        InvoiceImporter $invoiceImporter,
        CompanyName $companyName,
        BankAccount $bankAccount,
        InvoiceProcessor $invoiceProcessor,
        User $user
    ) {
        $this->statementImporter = $statementImporter;
        $this->invoiceImporter = $invoiceImporter;
        $this->companyName = $companyName;
        $this->invoiceProcessor = $invoiceProcessor;
        $this->bankAccount = $bankAccount;
        $this->user = $user;
        $this->xmlStatementImporter = $xmlStatementImporter;
    }

    public function index(Request $request)
    {
        if ($request->user()) {
            $userId = $request->user()->id;
            $userName = $request->user()->name;

            $response = [
                'userName' => $userName,
                'userId' => $userId,
                'companyNames' => $this->companyName->getNames($userId),
                'bankAccounts' => $this->bankAccount->getAccounts($userId),
                'success' => $request->message
            ];
            return view('process_invoice')->with($response);
        }

        return view('home');
    }


    public function processInvoice(Request $request)
    {
        $this->validateForm($request);

        $this->separator = empty($request->separator) ? $this->separator : $request->separator;

        if ($request->hasFile('bankStatement') && $request->file('bankStatement')->isValid()) {
            $file = $request->file('bankStatement');
            $path = $file->getRealPath();
            $this->statementImporter->setSeparator($this->separator);
            if ($file->getClientMimeType() == 'text/xml' || $file->getMimeType() == 'text/xml') {
                $this->xmlStatementImporter->importBankStatement($path, $request->userId);
            } else {
                $this->statementImporter->importBankStatement($path, $request->userId);
            }
        }

        if ($request->hasFile('openInvoice') && $request->file('openInvoice')->isValid()) {
            $file = $request->file('openInvoice');
            $path = $file->getRealPath();
            $this->invoiceImporter->setSeparator($this->separator);
            $this->invoiceImporter->importOpenInvoice($path, $request->userId);
        }

        $this->export = $this->invoiceProcessor->processInvoice($request->userId);
        
        // remove user data from database before export
//        $this->statementImporter->truncateDBForUser($request->userId);
//        $this->invoiceImporter->truncateDBForUser($request->userId);

        return $this->streamResponse($this->user->getNameById($request->userId));
    }


    private function validateForm($request)
    {
        $rules = [
            'bankStatement' => '
                required
                |
                mimetypes:text/xml,text/plain,text/x-Algol68,application/csv,application/excel,application/vnd.ms-excel,application/vnd.msexcel,text/csv,text/anytext,text/comma-separated-values',
            'openInvoice' => '
                required
                |
                mimetypes:text/xml,text/plain,text/x-Algol68,application/csv,application/excel,application/vnd.ms-excel,application/vnd.msexcel,text/csv,text/anytext,text/comma-separated-values',
        ];

        $customMessages = [
            'required' => 'The :attribute field is required.',
            'mimetypes' => ':attribute not a valid csv file'
        ];

        $this->validate($request, $rules, $customMessages);
        
    }

    private function streamResponse(string $userName)
    {
        $columnHeadings = [
            'Bank Payment Line',
            'Date',
            'Customer Account',
            'Invoice Number',
            'Currency',
            'Amount',
            'Payment Reference',
            'Payee Name',
            'ERP Name',
            'Matching method',
            'Statement amount',
            'Invoice open amount',
            'Partial payment'
        ];
        $sortedExport = [];
        foreach ($this->export as $row) {
            $sortedExport[] = array_combine($columnHeadings, $row);
        }

//        usort($sortedExport, function($a, $b) {
//            return $a['Amount'] <=> $b['Amount'];
//        });

        return new StreamedResponse(function() use ($columnHeadings, $sortedExport){
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $columnHeadings, $this->separator);

            foreach ($sortedExport as $row) {
                fputcsv($handle, $row, $this->separator);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="export_'.$userName.'_'.date("Ymd_Hi").'.csv"',
        ]);
    }
}
