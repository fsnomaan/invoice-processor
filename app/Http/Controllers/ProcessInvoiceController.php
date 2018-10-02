<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BankStatement;
use App\Models\OpenInvoice;

class ProcessInvoiceController extends Controller
{
    /** @var BankStatement */
    private $bs;

    /** @var OpenInvoice */
    private $openInvoice;

    private $export = [];

    public function __construct(BankStatement $bs, OpenInvoice $openInvoice)
    {
        $this->bs = $bs;
        $this->openInvoice = $openInvoice;
        $this->export = [];
    }

    public function processInvoice(Request $request)
    {
        $bsRows = $this->bs->getRowsLikeInvoice('1125-');
        foreach($bsRows as $bsRow) {
            $openInvoiceTotal = 0;
            $input = trim(preg_replace('/\s+/', '', $bsRow->purpose_of_use));
            preg_match_all("/1125-\d{6}/", $input, $bsInvoices);
            $openInvoiceRows = $this->openInvoice->getRowsFromInvoices($bsInvoices[0]);
            foreach($openInvoiceRows as $openInvoiceRow) {
                try {
                    $openInvoiceTotal += $openInvoiceRow->amount_transaction_currency;
                } catch (\Exception $e) {
                    dd($openInvoiceRow->toArray());
                }
            }
            
            if ( $this->isTotalMatches($bsRow->original_amount, $openInvoiceTotal)) {
                foreach($openInvoiceRows as $openInvoiceRow) {
                    $this->export[] = [$bsRow->trans_date, $openInvoiceRow->customer_number, $bsRow->purpose_of_use, '', $openInvoiceRow->amount_transaction_currency,
                                        $bsRow->original_currency, $bsRow->company_customer, $bsRow->trans_date, '01'];
                }
                dd($this->export);
            }
        }
    }

    private function isTotalMatches($bsTotal, $openInvoiceTotal)
    {
        return $bsTotal == $openInvoiceTotal;
    }
}
