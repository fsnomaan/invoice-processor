<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function getAllInvoiceNumbers()
    {
        return Model::where('invoice_number', '<>', '')->pluck('invoice_number');
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
       DB::enableQueryLog();
        $results = Model::where('amount_transaction', trim($total))
            ->where('name', 'LIKE', '%' . $name . '%')->get();
        return $results;
    }

    public function deleteById(int $userId)
    {
        Model::where('user_id', $userId)->delete();
    }
}
