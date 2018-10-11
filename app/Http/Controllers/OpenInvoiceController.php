<?php

namespace App\Http\Controllers;

use App\Models\OpenInvoice;
use App\Models\ColumnNames\OpenInvoice  as ColumnNames;
use Illuminate\Http\Request;

class OpenInvoiceController extends Controller
{
    /** @var OpenInvoice  */
    private $openInvoice;

    public function __construct(OpenInvoice $openInvoice)
    {
        $this->openInvoice = empty($openInvoice) ? new OpenInvoice() : $openInvoice;
    }

    public function processOpenInvoice(Request $request)
    {
        $this->validateForm($request);

        if ($request->hasFile('OpenInvoice') && $request->file('OpenInvoice')->isValid()) {
            $file = $request->file('OpenInvoice');
            $path = $file->getRealPath();
            if ($this->importOpenInvoice($path) ) {
                $response = [
                    'success' => 'Successfully imported invoice'
                ];
            }
            return view('process_invoice')->with($response);
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
        $dataTable = $this->getCsvData($path);
        $dataTable = $this->sanitize($dataTable);

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

    private function getCsvData($path)
    {
        $dataTable = [];
        $this->openInvoice->truncate();
        if (($h = fopen($path, "r")) !== FALSE) {
            $heading = fgetcsv($h, 1000, ";");
            if (count($heading) > 10 ) {
                dd("Extra columns found! Please remove extra ';' from end of each line");
            }
            while (($data = fgetcsv($h, 1000, ";")) !== FALSE) {
                $dataTable[] = array_combine(array_keys(ColumnNames::MAP), $data);
            }
        fclose($h);
        }

        return $dataTable;
    }
    
    private function sanitize($dataTable)
    {
        foreach($dataTable as $k => $dt) {
            if ($dataTable[$k]['amount_transaction'] < 0 ) {
                unset($dataTable[$k]);
            }
        }

        return $dataTable;
    }
}
