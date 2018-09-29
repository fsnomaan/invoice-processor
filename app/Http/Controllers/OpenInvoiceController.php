<?php

namespace App\Http\Controllers;

use App\Models\OpenInvoice;
use App\Models\ColumnNames\OpenInvoice  as ColumnNames;
use Illuminate\Http\Request;
use Illuminate\Http\Testing\MimeType;
use App\Http\Requests;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Input;

class OpenInvoiceController extends Controller
{
    /** @var OpenInvoice  */
    private $openInvoice;

    public function __construct(OpenInvoice $openInvoice)
    {
        $this->openInvoice = empty($openInvoice) ? new OpenInvoice() : $openInvoice;
    }

    public function index()
    {
        $response = [
            'success' => ''
        ];
        return view('open_invoice')->with($response);
    }

    public function processOpenInvoice(Request $request)
    {
        $this->validateForm($request);

        if ($request->hasFile('OpenInvoice') && $request->file('OpenInvoice')->isValid()) {
            $file = $request->file('OpenInvoice');
            $path = $file->getRealPath();
            if ($this->importOpenInvoice($path) ) {
                $response = [
                    'success' => 'Successfully imported'
                ];
            }
            return view('open_invoice')->with($response);
        }

    }

    private function validateForm($request)
    {
        $rules = [
            'OpenInvoice' => '
                required
                |
                mimetypes:text/plain,
                application/csv,
                application/excel,
                application/vnd.ms-excel,
                application/vnd.msexcel,
                text/csv,
                text/anytext,
                text/comma-separated-values',
        ];
    
        $customMessages = [
            'required' => 'The :attribute field is required.',
            'mimetypes' => 'not a valid csv file'
        ];
    
        $this->validate($request, $rules, $customMessages);
    }

    private function importOpenInvoice($path)
    {
        $dataTable = [];
        $this->openInvoice->truncate();
        if (($h = fopen($path, "r")) !== FALSE) {
            $columnHeadings = fgetcsv($h, 1000, ";");
            // dd($columnHeadings);
            // dd(array_keys(ColumnNames::MAP));
            while (($data = fgetcsv($h, 1000, ";")) !== FALSE) {		
                // dd($data);
                $dataTable[] = array_combine(array_keys(ColumnNames::MAP), $data);
            }
        fclose($h);
        }
        try {
            foreach (array_chunk($dataTable,1000) as $t) {
                $this->openInvoice->insert($t);
            }
        } catch(\Exception $e) {
            dd($e);
            return false;
        }

        return true;
    }
}
