<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CompanyName
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $map_to
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName whereMapTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CompanyName whereUserId($value)
 * @mixin \Eloquent
 */
class CompanyName extends Model
{
    protected $table = 'company_name';

    public function getNames(int $userId)
    {
        return Model::where('user_id', $userId)->get();
    }

    public function getNamesMap(int $userId)
    {
        return Model::where('user_id', $userId)->pluck('map_to', 'name')->toArray();
    }

    public function getByName(string $name, int $userId)
    {
        return Model::where('user_id', $userId)
            ->where('name', $name)->select('name', 'map_to')
            ->pluck('map_to', 'name')->first();
    }
}
