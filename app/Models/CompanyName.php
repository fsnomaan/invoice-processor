<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyName extends Model
{
    protected $table = 'company_name';

    public function getNames(int $userId)
    {
        return Model::where('user_id', $userId)->pluck('map_to', 'name')->toArray();
    }
}
