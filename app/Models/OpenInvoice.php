<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\AssignOp\Mod;

/**
 * App\Models\OpenInvoice
 *
 * @property int $id
 * @property int $user_id
 * @property string $customer_account
 * @property string $customer_name
 * @property string $invoice_number
 * @property string $currency_code
 * @property string $open_amount
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereCustomerAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereOpenAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\OpenInvoice whereUserId($value)
 * @mixin \Eloquent
 */
class OpenInvoice extends Model
{
    protected $table = 'open_invoice';

    public function getByInvoiceNumber(string $invoiceNumber)
    {
        return Model::where('invoice_number', $invoiceNumber)->first();
    }

    public function getRowsFromInvoices(array $invoiceNumbers)
    {
        return Model::whereIn('invoice_number', $invoiceNumbers)->distinct()->get();
    }

    public function getAllInvoiceNumbers(int $userId)
    {
        return Model::where('invoice_number', '<>', '')
            ->where('user_id', $userId)
            ->pluck('invoice_number');
    }

    public function getInvoiceByAmount(float $amount)
    {
        return Model::where('open_amount', $amount)->get();
    }

    public function getInvoiceByMatchingName(string $name, array $invoices)
    {
        return Model::where('name', 'LIKE', '%' . $name . '%')
            ->whereIn('invoice', $invoices)->get();
    }

    public function getInvoiceFromTotalAndName(float $total, string $name)
    {
        $results = Model::where('amount_transaction', trim($total))
            ->where('name', 'LIKE', '%' . $name . '%')->get();
        return $results;
    }

    public function deleteById(int $userId)
    {
        Model::where('user_id', $userId)->delete();
    }

    public function getAccountGroupedByTotal(float $amount)
    {
        return Model::select('customer_account', 'customer_name', 'currency_code', DB::raw('SUM(open_amount) as total'))
            ->groupBy('customer_account', 'customer_name', 'currency_code')
            ->having('total', '=', $amount)
            ->get();
    }
}
