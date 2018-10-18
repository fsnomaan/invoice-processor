<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyName extends Model
{
    protected $table = 'company_name';

    public function getNames()
    {
        return Model::pluck('map_to', 'name')->toArray();
    }
}
