<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use function Deployer\get;

ini_set('max_execution_time', 300);

class InvoiceProcessor
{
    /** @var int */
    private $userId;

    /** @var BankStatement */
    private $bs;

    /** @var OpenInvoice */
    private $openInvoice;

    /** @var array */
    private $invoiceNumbers = [];

    /** @var array */
    private $bsRowSequence = [];

    private $uniqueCustomers = [];

    /** @var array */
    private $export = [];

    /** @var CompanyName */
    private $companyName;

    /** @var BankAccount */
    private $bankAccount;

    /** @var string */
    private $message = '';

    /** @var bool */
    private $isPartialPayment = false;

    private $isOverPayment = false;
    
    /** @var array */
    private $bankAccountIdMap;
    
    public function __construct(
        BankStatement $bs,
        OpenInvoice $openInvoice,
        CompanyName $companyName,
        BankAccount $bankAccount
    ) {
        $this->export = [];
        $this->bs = $bs;
        $this->openInvoice = $openInvoice;
        $this->companyName = $companyName;
        $this->bankAccount = $bankAccount;
    }

    public function processInvoice(int $userId): array
    {
        $this->invoiceNumbers = $this->openInvoice->getAllInvoiceNumbers($userId)->toArray();
        $this->bsRowSequence = $this->bs->getSequence($userId)->toArray();
        $this->userId = $userId;
        $this->bankAccountIdMap = $this->bankAccount->getAccountsMap((int)$this->userId);
    
        $this->matchByInvNumberWhenStatementEqualsInvoice("payment_ref");
        $this->matchByInvNumberWhenStatementEqualsInvoice("payee_name");
    
        $this->matchByMultipleInvoiceWhenStatementEqualsInvoice("payment_ref");
        $this->matchByMultipleInvoiceWhenStatementEqualsInvoice("payee_name");
    
        $this->matchByNameMapWhenStatementEqualsInvoice("payment_ref");
        $this->matchByNameMapWhenStatementEqualsInvoice("payee_name");
    
        $this->matchByNameMapWhenStatementEqualsMultipleInvoice("payment_ref");
        $this->matchByNameMapWhenStatementEqualsMultipleInvoice("payee_name");
    
        $this->matchByNameMapWhenStatementEqualsSumOfMultipleInvoice("payment_ref");
        $this->matchByNameMapWhenStatementEqualsSumOfMultipleInvoice("payee_name");
    
        $this->matchByNameMapWhenStatementNotEqualsSumOfMultipleInvoice("payment_ref");
        $this->matchByNameMapWhenStatementNotEqualsSumOfMultipleInvoice("payee_name");
    
        $this->matchByAccountNameWhenStatementEqualsInvoice("payment_ref");
        $this->matchByAccountNameWhenStatementEqualsInvoice("payee_name");
    
        $this->matchByAccountNameWhenStatementEqualsMultipleInvoice("payment_ref");
        $this->matchByAccountNameWhenStatementEqualsMultipleInvoice("payee_name");
    
        $this->matchByAccountNameWhenStatementEqualsSumOfMultipleInvoice("payment_ref");
        $this->matchByAccountNameWhenStatementEqualsSumOfMultipleInvoice("payee_name");
    
        $this->matchByAccountNameWhenStatementNotEqualsSumOfMultipleInvoice("payment_ref");
    
        $this->matchByTotalWhenStatementEqualsInvoice("payment_ref");
        $this->matchByTotalWhenStatementEqualsInvoice("payee_name");
    
        $this->matchByTotalWhenStatementEqualsInvoiceTotal("payment_ref");
        $this->matchByTotalWhenStatementEqualsInvoiceTotal("payee_name");
    
        // $this->matchByTotalWhenStatementDoesNotEqualsInvoiceTotal("payment_ref");
    
        $this->matchByInvoiceAmountWhenStatementEqualsInvoice("payment_ref");
        $this->matchByInvoiceAmountWhenStatementEqualsInvoice("payee_name");

//        $this->matchByInvoiceAmountWhenStatementEqualsMultipleInvoices("payment_ref");
//        $this->matchByInvoiceAmountWhenStatementEqualsMultipleInvoices("payee_name");
    
        $this->matchByAccountTotalWhenStatementEqualsSumOfInvoices("payment_ref");
        $this->matchByAccountTotalWhenStatementEqualsSumOfInvoices("payee_name");
    
        $this->matchByPayeeNameOnly("payment_ref");
        $this->matchByPayeeNameOnly("payee_name");
    
        $this->matchByInvNumberWhenStatementGreaterThanInvoice("payment_ref");
        // $this->matchByInvNumberWhenStatementGreaterThanInvoice("payee_name");
    
        $this->matchByInvNumberWhenStatementLowerThanInvoice("payment_ref");
        //$this->matchByInvNumberWhenStatementLowerThanInvoice("payee_name");
    
        //  $this->matchByTotalWhenStatementDoesNotEqualsInvoiceTotal("payee_name");
    
        $this->matchByAccountNameWhenStatementNotEqualsSumOfMultipleInvoice("payee_name");
        $this->matchByMultipleInvoiceWhenStatementNotEqualsInvoice("payment_ref");
        //$this->matchByMultipleInvoiceWhenStatementNotEqualsInvoice("payee_name");
    
        $this->exportUnmatchedStatementRows();


//        dd($this->export);
        return $this->export;
    }

    private function matchByInvNumberWhenStatementEqualsInvoice(string $searchField)
    {
        
        if ( empty($this->bsRowSequence) ) { return; }

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) == 1) {
                        $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                        if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                            $this->message = $this->getMessage(__FUNCTION__)['message'];
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getMessage(__FUNCTION__)['manualCheck']);
                        }
                    }
                }
            }
        }
    }

    private function matchByInvNumberWhenStatementGreaterThanInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) == 1) {
                        $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                        if ((float)$bsRow->amount > (float)$openInvoiceRow->open_amount) {
                            $this->message = $this->getMessage(__FUNCTION__)['message'];
                            $this->isOverPayment = true;
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getMessage(__FUNCTION__)['manualCheck']);
                        }
                    }
                }
            }
        }
    }

    private function matchByInvNumberWhenStatementLowerThanInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) == 1) {
                        $openInvoiceRow = $this->openInvoice->getByInvoiceNumber($matchingInvoiceNumbers[0]);
                        if ((float)$bsRow->amount < (float)$openInvoiceRow->open_amount) {
                            $this->message = $this->getMessage(__FUNCTION__)['message'];
                            $this->isPartialPayment = true;
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getMessage(__FUNCTION__)['manualCheck']);
                        }
                    }
                }
            }
        }
    }

    private function matchByMultipleInvoiceWhenStatementEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) > 1) {
                        /** @var Collection $openInvoiceRows */
                        $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoiceNumbers);
                        $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                        if ( (float)$openInvoiceTotal == (float)$bsRow->amount )  {
                            if ($this->isAllForSameAccount($openInvoiceRows->toArray())) {
                                foreach($openInvoiceRows as $openInvoiceRow) {
                                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                                    $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getMessage(__FUNCTION__)['manualCheck']);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function matchByMultipleInvoiceWhenStatementNotEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            $bsRows = $this->bs->findBySearchField($invoiceNumber, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                if (! empty($bsRow) && in_array($bsRow->sequence, $this->bsRowSequence)) {
                    $matchingInvoiceNumbers = $this->getMatchingInvoiceNumbers($bsRow, $searchField);
                    if (count($matchingInvoiceNumbers) > 1) {
                        /** @var Collection $openInvoiceRows */
                        $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($matchingInvoiceNumbers);
                        $openInvoiceTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                        if ( (float)$openInvoiceTotal != (float)$bsRow->amount )  {
                            if ($this->isAllForSameAccount($openInvoiceRows->toArray())) {
                                $this->message = $this->getMessage(__FUNCTION__)['message'];
                                $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $this->getMessage(__FUNCTION__)['manualCheck'], $openInvoiceRows[0]);
                            }
                        }
                    }
                }
            }
        }
    }

    private function isAllForSameAccount(array $invoiceRows): bool
    {
        $accounts = array_column($invoiceRows, 'customer_account');
        if (count(array_unique($accounts)) === 1) {
            return true;
        }

        return false;
    }

    private function matchByTotalWhenStatementEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $matchedInvoices = $this->getMatchedInvoiceForAmount($bsRow, $searchField);

            if ( $matchedInvoices ) {
                if (count($matchedInvoices) == 1) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithMatch($bsRow, $matchedInvoices[0], $this->getMessage(__FUNCTION__)['manualCheck']);
                }
            }
        }
    }

    private function matchByTotalWhenStatementEqualsInvoiceTotal(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $matchedInvoices = $this->getMatchedInvoiceForAmount($bsRow, $searchField);

            if ( $matchedInvoices ) {
                if (count($matchedInvoices) > 1) {
                    $invoicesTotal = $this->getOpenInvoicesTotal($matchedInvoices);
                    if ((float)$invoicesTotal == (float)$bsRow->amount) {
                        foreach ($matchedInvoices as $openInvoiceRow) {
                            $this->message = $this->getMessage(__FUNCTION__)['message'];
                            $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getMessage(__FUNCTION__)['manualCheck']);
                        }
                    }
                }
            }
        }
    }

//    private function matchByTotalWhenStatementDoesNotEqualsInvoiceTotal(string $searchField)
//    {
//        if ( empty($this->bsRowSequence) ) { return; }
//
//        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
//        foreach ($bsRows as $bsRow) {
//            $matchedInvoices = $this->getMatchedInvoiceForAmount($bsRow, $searchField);
//
//            if ( $matchedInvoices ) {
//                if (count($matchedInvoices) > 1) {
//                    $invoicesTotal = $this->getOpenInvoicesTotal($matchedInvoices);
//                    if ( (float)$invoicesTotal != (float)$bsRow->amount ) {
//                        $this->message = $this->getMessage(__FUNCTION__)['message'];
//                        $this->isOverPayment = true;
//                        $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $matchedInvoices[0]);
//                    }
//                }
//            }
//        }
//    }

    private function getMatchedInvoiceForAmount(BankStatement $bsRow, string $searchField)
    {
        $invoices = $this->openInvoice->getByAmount((float)$bsRow->amount, $this->userId, $this->invoiceNumbers);
        $matchedInvoices = [];
        foreach ($invoices as $invoice) {
            $nameMap = $this->companyName->getByName($invoice->customer_name, $this->userId);
            if ($nameMap) {
                $nameMap = trim($nameMap);
            } else {
                $nameMap = $invoice->customer_name;

            }

            if (strpos(strtolower($bsRow->$searchField), strtolower($nameMap) ) !== false
            ) {
                $matchedInvoices[] = $invoice;
            }
        }
        return $matchedInvoices;
    }

    private function matchByAccountNameWhenStatementEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;
            $bsRows = $this->bs->findByERPName($customerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {

                $matchedInvoices = [];
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                foreach ($openInvoiceRows as $openInvoiceRow) {
                    if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                        $matchedInvoices[] = $openInvoiceRow;
                    }
                }
                if (count($matchedInvoices) == 1) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithMatch($bsRow, $matchedInvoices[0], $this->getMessage(__FUNCTION__)['manualCheck']);
                    $this->deleteElement($customerName, $this->uniqueCustomers);

                }
            }
        }
    }

    private function matchByAccountNameWhenStatementEqualsMultipleInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;
            $bsRows = $this->bs->findByERPName($customerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $matchedInvoices = [];
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                foreach ($openInvoiceRows as $openInvoiceRow) {
                    if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                        $matchedInvoices[] = $openInvoiceRow;
                    }
                }
                if (count($matchedInvoices) > 1) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->isOverPayment = true;
                    $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $matchedInvoices[0]);
                    $this->deleteElement($customerName, $this->uniqueCustomers);
                }
            }
        }
    }

    private function matchByAccountNameWhenStatementEqualsSumOfMultipleInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;
            $bsRows = $this->bs->findByERPName($customerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);

                $invoicesTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                if ( (float)$invoicesTotal == (float)$bsRow->amount ) {
                    foreach ($openInvoiceRows as $openInvoiceRow) {
                        $this->message = $this->getMessage(__FUNCTION__)['message'];
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getMessage(__FUNCTION__)['manualCheck']);
                        $this->deleteElement($customerName, $this->uniqueCustomers);

                    }
                }
            }
        }
    }

    private function matchByAccountNameWhenStatementNotEqualsSumOfMultipleInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;
            $bsRows = $this->bs->findByERPName($customerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                $invoicesTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                if ( (float)$invoicesTotal != (float)$bsRow->amount ) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $openInvoiceRows[0]);
                    $this->deleteElement($customerName, $this->uniqueCustomers);
                }
            }
        }
    }

    private function matchByNameMapWhenStatementEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();
        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;

            $mappedCustomerName = $this->companyName->getByName($customerName, $this->userId);
            if ( !$mappedCustomerName ) continue;

            $bsRows = $this->bs->findByERPName($mappedCustomerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {

                $matchedInvoices = [];
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                foreach ($openInvoiceRows as $openInvoiceRow) {
                    if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                        $matchedInvoices[] = $openInvoiceRow;
                    }
                }
                if (count($matchedInvoices) == 1) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithMatch($bsRow, $matchedInvoices[0], $this->getMessage(__FUNCTION__)['manualCheck']);
                    $this->deleteElement($customerName, $this->uniqueCustomers);
                }
            }
        }
    }

    private function matchByNameMapWhenStatementEqualsMultipleInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;

            $mappedCustomerName = $this->companyName->getByName($customerName, $this->userId);
            if ( !$mappedCustomerName ) continue;

            $bsRows = $this->bs->findByERPName($mappedCustomerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $matchedInvoices = [];
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                foreach ($openInvoiceRows as $openInvoiceRow) {
                    if ((float)$openInvoiceRow->open_amount == (float)$bsRow->amount) {
                        $matchedInvoices[] = $openInvoiceRow;
                    }
                }
                if (count($matchedInvoices) > 1) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->isOverPayment = true;
                    $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $matchedInvoices[0]);
                    $this->deleteElement($customerName, $this->uniqueCustomers);
                }
            }
        }
    }

    private function matchByNameMapWhenStatementEqualsSumOfMultipleInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;

            $mappedCustomerName = $this->companyName->getByName($customerName, $this->userId);
            if ( !$mappedCustomerName ) continue;

            $bsRows = $this->bs->findByERPName($mappedCustomerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);

                $invoicesTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                if ( (float)$invoicesTotal == (float)$bsRow->amount ) {
                    foreach ($openInvoiceRows as $openInvoiceRow) {
                        $this->message = $this->getMessage(__FUNCTION__)['message'];
                        $this->exportRowsWithMatch($bsRow, $openInvoiceRow, $this->getMessage(__FUNCTION__)['manualCheck']);
                        $this->deleteElement($customerName, $this->uniqueCustomers);
                    }
                }
            }
        }
    }

    private function matchByNameMapWhenStatementNotEqualsSumOfMultipleInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $uniqueCustomers = $this->openInvoice->getUniqueCustomerNames($this->userId, $this->invoiceNumbers)->toArray();

        foreach ($uniqueCustomers as $customerName) {
            if ( !$customerName ) continue;

            $mappedCustomerName = $this->companyName->getByName($customerName, $this->userId);
            if ( !$mappedCustomerName ) continue;

            $bsRows = $this->bs->findByERPName($mappedCustomerName, $this->userId, $searchField);
            foreach ($bsRows as $bsRow) {
                $openInvoiceRows = $this->openInvoice->getByCustomerName($customerName, $this->userId);
                $invoicesTotal = $this->getOpenInvoicesTotal($openInvoiceRows->toArray());
                if ( (float)$invoicesTotal != (float)$bsRow->amount ) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $openInvoiceRows[0]);
                    $this->deleteElement($customerName, $this->uniqueCustomers);
                }
            }
        }
    }

    private function matchByInvoiceAmountWhenStatementEqualsInvoice(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            for($i=7; $i<=10; $i++) {
                $needles = $this->getWordsByPosition($bsRow->$searchField, $i);
                if ( empty(trim($needles)) ) { return; }
    
                /** @var Collection $matchedInvoices */
                $matchedInvoices = $this->openInvoice->getByAmountAndName(
                    (float)$bsRow->amount,
                    $this->userId,
                    $this->invoiceNumbers,
                    $needles
                );
    
                if ( count($matchedInvoices) == 1 ) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithMatch($bsRow, $matchedInvoices[0], $this->getMessage(__FUNCTION__)['manualCheck']);
                    break;
                } elseif ( count($matchedInvoices) > 1 && $this->isAllForSameAccount($matchedInvoices->toArray()) ) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $matchedInvoices[0]);
                    break;
                }
            }
        }
    }

//    private function matchByInvoiceAmountWhenStatementEqualsMultipleInvoices(string $searchField)
//    {
//        if ( empty($this->bsRowSequence) ) { return; }
//
//        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
//        foreach ($bsRows as $bsRow) {
//            $needles = $this->getWordsByPosition($bsRow->$searchField);
//            if ( empty(trim($needles)) ) { return; }
//
//            /** @var Collection $matchedInvoices */
//            $matchedInvoices = $this->openInvoice->getByAmountAndName(
//                (float)$bsRow->amount,
//                $this->userId,
//                $this->invoiceNumbers,
//                $needles
//            );
//
//            if ( count($matchedInvoices) == 1) {
//                $this->message = $this->getMessage(__FUNCTION__)['message'];
//                $this->exportRowsWithMatch($bsRow, $matchedInvoices[0], $this->getMessage(__FUNCTION__)['manualCheck']);
//            } elseif (count($matchedInvoices) > 1 && $this->isAllForSameAccount($matchedInvoices->toArray())) {
//                $this->message = $this->getMessage(__FUNCTION__)['message'];
//                $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $matchedInvoices[0]);
//            }
//        }
//    }

    private function matchByAccountTotalWhenStatementEqualsSumOfInvoices(string $searchField)
    {
        if ( empty($this->bsRowSequence) ) { return; }

        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);

        /** @var BankStatement $bsRow */
        foreach ($bsRows as $bsRow) {
            for($i=7; $i<=10; $i++) {
                $needles = $this->getWordsByPosition($bsRow->$searchField, $i);
                if ( empty(trim($needles)) ) { return; }
    
                $matchedInvoices = $this->openInvoice->getAccountGroupedByTotalForName(
                    $bsRow->original_amount,
                    $this->userId,
                    $this->invoiceNumbers,
                    $needles
                );
    
                if ( count($matchedInvoices) == 1) {
                    $invoices = $this->openInvoice->getByCustomerAccount($matchedInvoices[0]->customer_account, $this->userId);
                    foreach ($invoices as $invoice) {
                        $this->message = $this->getMessage(__FUNCTION__)['message'];
                        $this->exportRowsWithMatch($bsRow, $invoice, $this->getMessage(__FUNCTION__)['manualCheck']);
                    }
                    break;
                }
            }
        }
    }
    
    private function matchByPayeeNameOnly(string $searchField)
    {
        $bsRows = $this->getBsRows();
        /** @var BankStatement $bsRow */
        foreach ($bsRows as $bsRow) {
            for($i=7; $i<=10; $i++) {
                $needles = $this->getWordsByPosition($bsRow->$searchField, $i);
                if ( empty(trim($needles)) ) { return; }
    
                /** @var Collection $invoices */
                $invoices = $this->openInvoice->getByCustomerName($needles, $this->userId);
                if ( count($invoices) == 1) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    if ((float)$bsRow->original_amount < (float)$invoices[0]->open_amount) {
                        $this->isPartialPayment = true;
                    } elseif ((float)$bsRow->original_amount > (float)$invoices[0]->open_amount) {
                        $this->isOverPayment = true;
                    }
                    $this->exportRowsWithMatch($bsRow, $invoices[0], $this->getMessage(__FUNCTION__)['manualCheck']);
                    break;
                } elseif (count($invoices) > 1 && $this->isAllForSameAccount($invoices->toArray())) {
                    $this->message = $this->getMessage(__FUNCTION__)['message'];
                    $this->exportRowsWithNoMatch($bsRow, $this->getMessage(__FUNCTION__)['manualCheck'], $invoices[0]);
                    break;
                }
            }
        }
    }
    
    private function getWordsByPosition(string $payeeName, $count = 7)
    {
        $payeeName = preg_replace('/\s+/', ' ', $payeeName);
        return substr(trim($payeeName), 0, $count);
    }
    
    private function getOpenInvoicesTotal(array $openInvoiceRows) : float
    {
        $openInvoiceTotal = 0;
        foreach($openInvoiceRows as $openInvoiceRow) {
            $openInvoiceTotal += (float) $openInvoiceRow['open_amount'];
        }

        return $openInvoiceTotal;
    }

    private function getMatchingInvoiceNumbers(BankStatement $bsRow, string $searchField, bool $partial=false) : array
    {
        $matchingInvoiceNumbers = [];

        foreach ($this->invoiceNumbers as $invoiceNumber) {
            if ($partial) {
                $invoicePart = $this->getInvoicePart($invoiceNumber);
                if ($invoicePart) {
                    if (strpos(strtolower($bsRow->$searchField), trim(strtolower($invoicePart)) ) !== false) {
                        $matchingInvoiceNumbers[] = $invoiceNumber;
                    }
                }
            } else {
                if ($invoiceNumber) {
                    if (strpos(strtolower($bsRow->$searchField), trim(strtolower($invoiceNumber)) ) !== false) {
                        $matchingInvoiceNumbers[] = $invoiceNumber;
                    }
                }
            }
        }

        // remove duplicate invoice number
        return array_unique($matchingInvoiceNumbers);
    }

    private function getInvoicePart(string $invoiceNumber): ?string
    {
        $partial = preg_split('/\D[0]/', $invoiceNumber);

        return end($partial);
    }

    private function getBsRows() : Collection
    {
        if ( $this->bsRowSequence ) {
            return $this->bs->getByPaymentSequence($this->bsRowSequence);
        }
        
        return new Collection();
    }
    
    private function exportRowsWithMatch(BankStatement $bsRow, OpenInvoice $openInvoiceRow, string $manualCheck)
    {
        if ( !in_array($openInvoiceRow->invoice_number, $this->invoiceNumbers ) ) {
            return;
        }

        $this->export[] = [
            $bsRow->sequence,
            $bsRow->transaction_date,
            $openInvoiceRow->customer_account,
            $openInvoiceRow->invoice_number,
            $bsRow->currency,
            $this->getAmount($bsRow, $openInvoiceRow),
            $bsRow->payment_ref,
            $this->getBankAccountId($bsRow->bank_account_number),
            $bsRow->payee_name,
            $openInvoiceRow->customer_name,
            $this->message,
            $bsRow->original_amount,
            $openInvoiceRow->open_amount,
            $this->isPartialPayment ? 'Yes' : 'No',
            $this->isOverPayment ? 'Yes' : 'No',
            $manualCheck
        ];

        $this->deleteElement($bsRow->sequence, $this->bsRowSequence);
        if (!$this->isPartialPayment) {
            $this->deleteElement($openInvoiceRow->invoice_number, $this->invoiceNumbers);
}
        $this->resetMessage();
    }

    private function getAmount(BankStatement $bankStatement, OpenInvoice $openInvoice): float
    {
        if ($this->isOverPayment || $this->isPartialPayment) {
            return $bankStatement->original_amount;
        }

        return $openInvoice->open_amount;
    }

    private function deleteElement($needle, &$haystack){
        $index = array_search($needle, $haystack);
        if($index !== false){
            unset($haystack[$index]);
        }
    }
    
    private function exportRowsWithNoMatch(BankStatement $bsRow, string $manualCheck, OpenInvoice $invoice=null)
    {
        if ( !in_array($bsRow->sequence, $this->bsRowSequence ) ) {
            return;
        }
        $this->export[] = [
            $bsRow->sequence,
            $bsRow->transaction_date,
            $invoice ? $invoice->customer_account : '',
            '',
            $bsRow->currency,
            $bsRow->original_amount,
            $bsRow->payment_ref,
            $this->getBankAccountId($bsRow->bank_account_number),
            $bsRow->payee_name,
            $invoice ? $invoice->customer_name : '',
            $this->message,
            $bsRow->original_amount,
            '',
            $this->isPartialPayment ? 'Yes' : 'No',
            $this->isOverPayment ? 'Yes' : 'No',
            $manualCheck
        ];

        $this->deleteElement($bsRow->sequence, $this->bsRowSequence);
        $this->resetMessage();;
    }
    
    private function getBankAccountId(string $accountNumber)
    {
        return $this->bankAccountIdMap[$accountNumber] ?? "Insert account number";
    }
    
    private function resetMessage()
    {
        $this->message = '';
        $this->isPartialPayment = false;
        $this->isOverPayment = false;
    }
    
    private function exportUnmatchedStatementRows()
    {
        $bsRows = $this->bs->getByPaymentSequence($this->bsRowSequence);
        foreach ($bsRows as $bsRow) {
            $this->message = 'No Match Found';
            $this->exportRowsWithNoMatch($bsRow, 'N/A', null);
        }
    }
    
    private function getMessage(string $methodName)
    {
        $methodMap = [
            'matchByInvNumberWhenStatementEqualsInvoice' => [
                'message' => 'Invoice Number - Single Invoice',
                'manualCheck' => 'No'
            ],
            'matchByMultipleInvoiceWhenStatementEqualsInvoice' => [
                'message' => 'Invoice Number - Multiple Invoices',
                'manualCheck' => 'No'
            ],
            'matchByNameMapWhenStatementEqualsInvoice' => [
                'message' => 'Customer Mapping - Single Invoice',
                'manualCheck' => 'No'
            ],
            'matchByNameMapWhenStatementEqualsMultipleInvoice' => [
                'message' => 'Customer Mapping - Multiple Invoices',
                'manualCheck' => 'No'
            ],
            'matchByNameMapWhenStatementEqualsSumOfMultipleInvoice' => [
                'message' => 'Customer Mapping - Multiple Invoices',
                'manualCheck' => 'No'
            ],
            'matchByNameMapWhenStatementNotEqualsSumOfMultipleInvoice' => [
                'message' => 'Customer Mapping - Input Needed',
                'manualCheck' => 'Yes'
            ],
            'matchByAccountNameWhenStatementEqualsInvoice' => [
                'message' => 'ERP Name - Single Invoice',
                'manualCheck' => 'Yes'
            ],
            'matchByAccountNameWhenStatementEqualsMultipleInvoice' => [
                'message' => 'ERP Name - Multiple Invoices',
                'manualCheck' => 'Yes'
            ],
            'matchByAccountNameWhenStatementEqualsSumOfMultipleInvoice' => [
                'message' => 'ERP Name - Multiple Invoices',
                'manualCheck' => 'Yes'
            ],
            'matchByAccountNameWhenStatementNotEqualsSumOfMultipleInvoice' => [
                'message' => 'ERP Name - Input Needed',
                'manualCheck' => 'Yes'
            ],
            'matchByTotalWhenStatementEqualsInvoice' => [
                'message' => 'ERP Account Total - Single',
                'manualCheck' => 'InvoiceYes'
            ],
            'matchByTotalWhenStatementEqualsInvoiceTotal' => [
                'message' => 'ERP Account Total - Multiple',
                'manualCheck' => 'InvoicesYes'
            ],
            'matchByInvoiceAmountWhenStatementEqualsInvoice' => [
                'message' => 'Invoice Amount - Single Invoice',
                'manualCheck' => ''
            ],
            'matchByAccountTotalWhenStatementEqualsSumOfInvoices' => [
                'message' => 'ERP Account Total - Multiple',
                'manualCheck' => 'InvoicesYes'
            ],
            'matchByPayeeNameOnly' => [
                'message' => 'Payee Name - Input Needed',
                'manualCheck' => 'Yes'
            ],
            'matchByInvNumberWhenStatementGreaterThanInvoice' => [
                'message' => 'Invoice Number - Over Payment',
                'manualCheck' => 'Yes'
            ],
            'matchByInvNumberWhenStatementLowerThanInvoice' => [
                'message' => 'Invoice Number - Partial Payment',
                'manualCheck' => 'Yes'
            ],
            'matchByMultipleInvoiceWhenStatementNotEqualsInvoice' => [
                'message' => 'Invoice Number - Input Needed',
                'manualCheck' => 'Yes'
            ],
            'exportUnmatchedStatementRows' => [
                'message' => 'No Match Found',
                'manualCheck' => 'N/A'
            ]
        ];
        
        return $methodMap[$methodName] ?? '';
    }
}
