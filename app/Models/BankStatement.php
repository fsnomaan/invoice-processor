<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $table = 'bank_statement';

    public function getRowsLikeInvoice(string $invoice)
    {
        return Model::where('payment_ref', 'LIKE', '%' . $invoice . '%')
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

    public function getAllPaymentRefs()
    {
        return Model::where('payment_ref', '<>', '')->pluck('payment_ref');
    }

    public function getByPaymentRef(array $paymentRefs)
    {
        return Model::wherein('payment_ref', $paymentRefs)->get();
    }

    public function getByTotal($amount)
    {
        return Model::where('amount', $amount)->first();
    }

}
