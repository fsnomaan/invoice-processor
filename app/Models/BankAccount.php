<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\BankAccount
 *
 * @property int $id
 * @property int $user_id
 * @property string $bank_acc_number
 * @property string $bank_acc_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount whereBankAccId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount whereBankAccNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BankAccount whereUserId($value)
 * @mixin \Eloquent
 */
class BankAccount extends Model
{
    protected $table = 'bank_account';

    public function getAccounts(int $userId)
    {
        // return Model::where('user_id', $userId)->pluck('bank_acc_id', 'bank_acc_number')->toArray();
        return Model::where('user_id', $userId)->get();
    }

    public function getAccountsMap(int $userId)
    {
        return Model::where('user_id', $userId)->pluck('bank_acc_id', 'bank_acc_number')->toArray();
    }
}
