<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $table = 'bank_statement';

    public function getRowsLikeInvoice(string $invoice)
    {
        return Model::where('purpose_of_use', 'LIKE', '%' . $invoice . '%')
            ->first();

    }

    public function getUnmatchedRows(array $ids)
    {
        return Model::whereNotIn('id', $ids)
            ->get();
    }

    public function deleteById(int $userId)
    {
        Model::where('user_id', $userId)->delete();
    }
}
