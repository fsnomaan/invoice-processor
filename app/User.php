<?php

namespace App;

use Illuminate\Notifications\Notifiable;

class User extends \TCG\Voyager\Models\User
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function getIdByName(string $userName):?int
    {
        $user = User::where('name', '=', $userName)->first();
        if ($user) {
            return $user->id;
        }

        return null;
    }

    public function getNameById(int $userId): string
    {
        return User::where('id', '=', $userId)->first()->name;
    }
}
