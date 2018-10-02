<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $table = 'bank_statement';

    public function getRowsLikeInvoice(string $invoice)
    {
        return Model::where('purpose_of_use', 'LIKE', '%' . $invoice . '%')->get();
    }
}
