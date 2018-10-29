<?php
namespace App\Models;

use App\Models\ColumnNames\OpenInvoice  as ColumnNames;

class InvoiceImporter
{
    /** @var OpenInvoice  */
    private $openInvoice;

    private $separator = ';';

    public function __construct(OpenInvoice $openInvoice)
    {
        $this->openInvoice = $openInvoice;
    }

    public function importOpenInvoice($path)
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
            $heading = fgetcsv($h, 1000, $this->separator);
            while (($data = fgetcsv($h, 1000, $this->separator)) !== FALSE) {
                $data = array_slice($data, 0, count(ColumnNames::MAP));
                try {
                    $dataTable[] = array_combine(array_keys(ColumnNames::MAP), $data);
                } catch (\Exception $e) {
                    dd($e->getMessage(), $data);
                }
            }
            fclose($h);
        }

        return $dataTable;
    }

    private function sanitize($dataTable)
    {
        foreach($dataTable as $k => $dt) {
            $dataTable[$k]['Description'] = utf8_encode($dataTable[$k]['Description']);
            $dataTable[$k]['name'] = utf8_encode($dataTable[$k]['name']);
            if ($dataTable[$k]['amount_transaction'] < 0 ) {
                unset($dataTable[$k]);
            }
        }

        return $dataTable;
    }

    /**
     * @param string $separator
     */
    public function setSeparator(string $separator): void
    {
        $this->separator = $separator;
    }
}
