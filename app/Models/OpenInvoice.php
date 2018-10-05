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
}
