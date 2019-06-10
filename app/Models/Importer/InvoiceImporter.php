<?php
namespace App\Models\Importer;

use App\Models\OpenInvoice;

class InvoiceImporter
{
    /** @var OpenInvoice  */
    private $openInvoice;

    /** @var string $separator */
    private $separator = ';';

    /** @var int $userId */
    private $userId;

    public function __construct(OpenInvoice $openInvoice)
    {
        $this->openInvoice = $openInvoice;
    }

    public function importOpenInvoice($path, int $userId)
    {
        $this->userId = $userId;
        $this->truncateDbForUser($userId);
        $dataTable = $this->getCsvData($path);
        $dataTable = $this->sanitize($dataTable);

        try {
            foreach (array_chunk($dataTable, 1000) as $t) {
                $this->openInvoice->insert($t);
            }
        } catch(\Exception $e) {
            dd($e);
            return false;
        }

        unset($dataTable);
        
        return true;
    }

    public function truncateDbForUser(int $userId)
    {
        $this->openInvoice->deleteById($userId);
    }

    private function getCsvData($path)
    {
        $dataTable = [];
        if (($h = fopen($path, "r")) !== FALSE) {
            $heading = fgetcsv($h, 1000, $this->separator);
            while (($data = fgetcsv($h, 1000, $this->separator)) !== FALSE) {
                try {
                    $kvPair = array_combine($heading, $data);;
                    $kvPair['user_id'] = $this->userId;
                    $dataTable[] = $kvPair;
                } catch (\Exception $e) {
                    dd($e);
                }
            }
            fclose($h);
        }

        return $dataTable;
    }

    private function sanitize($dataTable)
    {
        foreach($dataTable as $k => $dt) {
            $dataTable[$k] = array_map('trim', $dt);
            $dataTable[$k]['open_amount'] = floatval(str_replace(",","",$dt['open_amount']));
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
