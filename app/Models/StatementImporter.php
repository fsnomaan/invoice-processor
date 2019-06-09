<?php
namespace App\Models;

use App\Models\ColumnNames\BankStatement as ColumnNames;

class StatementImporter
{
    /** @var BankStatement  */
    private $bs;

    /** @var string */
    private $invoicePrimary;

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
        $dataTable = $this->removeWithBookingText($dataTable, 'CASH CONCENTRATING BUCHUNG');
        $dataTable = $this->removeAwinRefunds($dataTable);
        $dataTable = $this->removeEmptyPayRef($dataTable);
        $dataTable = $this->removeNegativeAmount($dataTable);

        try {
            foreach (array_chunk($dataTable, 1000) as $t) {
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
            while (($data = fgetcsv($h, 1000, $this->separator)) !== FALSE) {
                $data = array_slice($data, 0, count(ColumnNames::MAP));
                try{
                    $kvPair = array_combine(array_keys(ColumnNames::MAP), $data);
                    $kvPair['user_id'] = $this->userId;
                    $dataTable[] = $kvPair;
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
            $dataTable[$k]['purpose_of_use'] = $this->standardiseInvoice($dt['purpose_of_use']);
            $dataTable[$k]['amount'] = floatval(str_replace(",","",$dt['amount']));
            $dataTable[$k]['original_amount'] = floatval(str_replace(",","",$dt['original_amount']));
        }

        return $dataTable;
    }

    private function standardiseInvoice(string $value)
    {
        $value = preg_replace("/" . $this->invoicePrimary . "\./", $this->invoicePrimary . " ", $value);
        $value = preg_replace("/" . $this->invoicePrimary . "/", $this->invoicePrimary . " ", $value);
        $value = preg_replace("/" . $this->invoicePrimary . " -/", $this->invoicePrimary . " ", $value);
        $value = preg_replace("/" . $this->invoicePrimary . "- /", $this->invoicePrimary . " ", $value);
        $value = preg_replace("/" . $this->invoicePrimary . " - /", $this->invoicePrimary . " ", $value);
        $value = trim(preg_replace('/\s+/', '', $value)); // REMOVE ANY NEW LINE
        $value = preg_replace("/" . $this->invoicePrimary . "\s*/", $this->invoicePrimary . "-", $value);

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

    private function removeEmptyPayRef(&$bsArray)
    {
        foreach ($bsArray as $key => $row) {
            if ( empty($row['company_customer']) ) {
                unset($bsArray[$key]);
            }
        }

        return $bsArray;
    }

    private function removeNegativeAmount(&$bsArray)
    {
        foreach ($bsArray as $key => $row) {
            if ( $row['amount'] < 0) {
                unset($bsArray[$key]);
            }
        }

        return $bsArray;
    }

    /**
     * @param string $invoicePrimary
     */
    public function setInvoicePrimary(string $invoicePrimary): void
    {
        $this->invoicePrimary = $invoicePrimary;
    }

    /**
     * @param string $separator
     */
    public function setSeparator(string $separator): void
    {
        $this->separator = $separator;
    }

}