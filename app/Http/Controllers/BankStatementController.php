<?php

namespace App\Http\Controllers;

use App\Models\BankStatement;
use App\Models\ColumnNames\BankStatement as ColumnNames;
use Illuminate\Http\Request;
use App\Http\Requests;

class BankStatementController extends Controller
{
    /** @var BankStatement  */
    private $bs;

    public function __construct(BankStatement $bs)
    {
        $this->bs = empty($bs) ? new BankStatement() : $bs;
    }

    public function processBankStatement(Request $request)
    {
        $this->validateForm($request);

        if ($request->hasFile('bankStatement') && $request->file('bankStatement')->isValid()) {
            $file = $request->file('bankStatement');
            $path = $file->getRealPath();
            if ($this->importBankStatement($path) ) {
                $response = [
                    'success' => 'Successfully imported bank statement'
                ];
            }
            return view('process_invoice')->with($response);
        }

    }

    private function validateForm($request)
    {
        $rules = [
            'bankStatement' => '
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

    private function importBankStatement($path)
    {
        $dataTable = $this->getCsvData($path);
        $dataTable = $this->sanitize($dataTable);
        $dataTable = $this->removeWithBookingText($dataTable, 'CASH CONCENTRATING BUCHUNG');
        $dataTable = $this->removeAwinRefunds($dataTable);

        try {
            foreach (array_chunk($dataTable,1000) as $t) {
                $this->bs->insert($t);
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
        $this->bs->truncate();
        if (($h = fopen($path, "r")) !== FALSE) {
            fgetcsv($h, 1000, ";");
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
            $dataTable[$k]['purpose_of_use'] = $this->standardiseInvoice($dt['purpose_of_use']);
        }

        return $dataTable;
    }

    private function standardiseInvoice(string $value)
    {
        $value = preg_replace("/1125\./", "1125 ", $value);
        $value = preg_replace("/1125/", "1125 ", $value);
        $value = preg_replace("/1125 -/", "1125 ", $value);
        $value = preg_replace("/1125- /", "1125 ", $value);
        $value = preg_replace("/1125 - /", "1125 ", $value);
        $value = preg_replace("/1125\s*/", "1125-", $value);

        $value = trim(preg_replace('/\s+/', '', $value));

        return $value;
    }

    private function removeWithBookingText(&$bsArray, $text)
    {
        foreach ($bsArray as $key => $row) {
            if (strpos($row['booking_text'], $text ) !== false) {
                unset($bsArray[$key]);
            }
        }

        return $bsArray;
    }

    private function removeAwinRefunds(&$bsArray)
    {
        foreach ($bsArray as $key => $row) {
            if (strpos($row['purpose_of_use'], 'AWINPAYOUT' ) !== false) {
                unset($bsArray[$key]);
            }
        }

        return $bsArray;
    }
}
