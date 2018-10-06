<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenInvoice extends Model
{
    protected $table = 'open_invoice';

    public function getRowsFromInvoices(array $invoices)
    {
        return Model::whereIn('invoice', $invoices)->get();
    }

    public function getAllInvoices()
    {
        return Model::where('invoice', '<>', '')->pluck('invoice');
    }

    public function getInvoiceByAmount(float $amount, array $excludeInvoices=null)
    {
        return Model::where('amount_transaction', $amount)
            ->whereNotIn('invoice', $excludeInvoices)->get();
    }

    public function getInvoiceByMatchingName(string $name, array $invoices)
    {
        return Model::where('name', 'LIKE', '%' . $name . '%')
            ->whereIn('invoice', $invoices)->get();
    }
}
