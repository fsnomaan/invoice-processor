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
        return Model::whereIn('invoice_number', $invoiceNumbers)->get();
    }

    public function getAllInvoiceNumbers(int $userId)
    {
        return Model::where('invoice_number', '<>', '')
            ->where('user_id', $userId)
            ->pluck('invoice_number');
    }

    public function getUniqueCustomerNames(int $userId, array $invoiceNumbers)
    {
        return Model::where('user_id', $userId)
            ->distinct()
            ->whereIn('invoice_number', $invoiceNumbers)
            ->pluck('customer_name');
    }

    public function getByAmount(float $amount, int $userId, array $invoiceNumbers)
    {
        return Model::where('open_amount', $amount)
            ->where('user_id', $userId)
            ->whereIn('invoice_number', $invoiceNumbers)
            ->get();
    }

    public function getByCustomerName(string $name, int $userId)
    {
        return Model::where('customer_name', 'LIKE', '%' . $name )
            ->where('user_id', $userId)
            ->get();
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

    public function getAccountGroupedByTotal(float $amount, int $userId, array $invoiceNumbers)
    {
        return Model::select('customer_account', 'customer_name', 'currency_code', DB::raw('SUM(open_amount) as total'))
            ->where('user_id', $userId)
            ->whereIn('invoice_number', $invoiceNumbers)
            ->groupBy('customer_account', 'customer_name', 'currency_code')
            ->having('total', '=', $amount)
            ->get();
    }

    public function getByCustomerAccount(string $customerAccount, int $userId)
    {
        return Model::where('customer_account', $customerAccount)
            ->where('user_id', $userId)
            ->get();
    }
}
