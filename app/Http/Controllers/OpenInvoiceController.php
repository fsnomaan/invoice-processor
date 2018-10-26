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

        if ($request->hasFile('openInvoice') && $request->file('openInvoice')->isValid()) {
            $file = $request->file('openInvoice');
            $path = $file->getRealPath();
            if ($this->importOpenInvoice($path) ) {
                session()->put('notifications','Invoice file imported: '. $file->getClientOriginalName());
                return redirect()->action(
                    'ProcessInvoiceController@index'
                );
            }
        }

    }

    private function validateForm($request)
    {
        $rules = [
            'openInvoice' => '
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
            $heading = fgetcsv($h, 1000, ",");
            while (($data = fgetcsv($h, 1000, ",")) !== FALSE) {
                $data = array_slice($data, 0, count(ColumnNames::MAP));
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
