<?php
namespace App\Models;

use Orchestra\Parser\Xml\Facade as XmlParser;

class XmlStatementImporter
{
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
        $dataTable = $this->getXmlData($path);
        $dataTable = $this->sanitize($dataTable);

        try {
            foreach (array_chunk($dataTable, 10) as $t) {
                foreach ($t as $row) {
                    $row['user_id'] = $this->userId;
                    dump($row);
                    $this->bs->insert($row);
                }
            }
        } catch(\Exception $e) {
            return false;
        }

        unset($dataTable);
        return true;
    }

    public function truncateDBForUser(int $userId): void
    {
        $this->bs->deleteById($userId);
    }

    private function getXmlData($path): array
    {
        /** @var XmlParser $xml */
        $xml = XmlParser::load($path);
        $dataTable = $xml->parse(
            [
                'transactions' => [
                    'uses' => 
                    'account.transactions.transaction[::type>trans_type,::code>trans_code,::status>trans_status,amount::currency>currency,amount,narrative>description]'
                ]
            ]
        );

        return $dataTable;
    }

    private function sanitize(array $dataTable): array
    {
        foreach($dataTable as $item) {
            $dataTable = $this->TrimArray($item);
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

    private function TrimArray($Input){
 
        if (!is_array($Input))
            return trim(trim(preg_replace('/\s+/', ' ', $Input)));
     
        return array_map(array($this, 'TrimArray'), $Input);
    }
}
