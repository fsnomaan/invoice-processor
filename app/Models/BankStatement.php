<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\BankStatement
 *
 * @property int $id
 * @property int $user_id
 * @property int $sequence
 * @property string $transaction_date
 * @property string $amount
 * @property string $currency
 * @property string|null $payment_ref
 * @property string|null $payee_name
 * @property string|null $original_amount
 * @property string|null $original_currency
 * @property string|null $bank_account_number
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereOriginalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereOriginalCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement wherePayeeName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement wherePaymentRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereTransactionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankStatement whereUserId($value)
 * @mixin \Eloquent
 */
class BankStatement extends Model
{
    protected $table = 'bank_statement';

    public function findBySearchField(string $needle, int $userId, string $searchField)
    {
        $rows = Model::where($searchField, 'LIKE', '%' . $needle . '%')
            ->where('user_id', $userId)
            ->get();

        return $rows;
    }

    public function findByERPName(string $name, int $userId, string $searchField)
    {
        $rows = Model::where($searchField, 'LIKE', ' ' . $name . '%')
            ->where('user_id', $userId)
            ->get();

        return $rows;
    }

    public function deleteById(int $userId)
    {
        Model::where('user_id', $userId)->delete();
    }

    public function getSequence(int $userId)
    {
        return Model::where('user_id', $userId)->pluck('sequence');
    }

    public function getByPaymentSequence(array $paymentSequence)
    {
        return Model::wherein('sequence', $paymentSequence)->get();
    }

    public function getAllPaymentRefs()
    {
        return Model::where('payment_ref', '<>', '')->pluck('payment_ref');
    }

    public function getByPaymentRefs(array $paymentRefs)
    {
        return Model::wherein('payment_ref', $paymentRefs)->get();
    }

    public function getByEmptyPaymentRefs()
    {
        return Model::where('payment_ref', '=', '')->get();
    }

    public function getByTotal($amount)
    {
        return Model::where('amount', $amount)->first();
    }

}
