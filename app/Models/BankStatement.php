<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $table = 'bank_statement';

    public function getByInvoiceNumber(string $invoice, int $userId)
    {
        return Model::where('payment_ref', 'LIKE', '%' . $invoice . '%')
            ->orwhere('payee_name', 'LIKE', '%' . $invoice . '%')
            ->where('user_id', $userId)
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
