<?php
namespace App\Models\Importer;

use App\Models\BankStatement;

class StatementImporter
{
    const COLUMNS = [];

    /** @var BankStatement  */
    private $bs;

    /** @var string  */
    private $separator = ';';

    /** @var int $userId */
    private $userId;

    public function __construct(BankStatement $bs)
    {
        $this->bs = $bs;
    }

    public function importBankStatement($path, int $userId): bool
    {
        $this->userId = $userId;

        $this->truncateDBForUser($this->userId);

        $dataTable = $this->getCsvData($path);
        $dataTable = $this->sanitize($dataTable);

        try {
            foreach (array_chunk($dataTable, 1000) as $t) {
//                dump($t);
                $this->bs->insert($t);
            }
        } catch(\Exception $e) {
            return false;
        }

        unset($dataTable);
        return true;
    }

    public function truncateDBForUser(int $userId)
    {
        $this->bs->deleteById($userId);
    }

    private function getCsvData($path)
    {
        $dataTable = [];
        if (($h = fopen($path, "r")) !== FALSE) {
            $heading = fgetcsv($h, 1000, $this->separator);
            $sequence = 0;
            while (($data = fgetcsv($h, 1000, $this->separator)) !== FALSE) {
                try{
                    $kvPair = array_combine($heading, $data);
                    $kvPair['user_id'] = $this->userId;
                    $kvPair['sequence'] = ++$sequence;
                    $dataTable[] = $kvPair;
                } catch (\Exception $e) {
                    dd($e->getMessage(), $data);
                }
            }
            fclose($h);
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

    private function sanitize($dataTable)
    {
        foreach($dataTable as $k => $dt) {
            $dataTable[$k] = array_map('trim', $dt);
            $dataTable[$k]['amount'] = floatval(str_replace(",","",$dt['amount']));
            $dataTable[$k]['original_amount'] = floatval(str_replace(",","",$dt['original_amount']));
            $dataTable[$k]['payment_ref'] = utf8_encode($dt['payment_ref']);
            $dataTable[$k]['payee_name'] = utf8_encode($dt['payee_name']);
        }
        return $dataTable;
    }
}
