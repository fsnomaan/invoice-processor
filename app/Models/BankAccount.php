<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
