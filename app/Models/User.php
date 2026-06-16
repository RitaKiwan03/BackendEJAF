<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'username',
        'password',
        'is_blocked', // ✅ مضاف — بدونه update() لا يعمل
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_admin'   => 'boolean',
        'is_blocked' => 'boolean', // ✅ مضاف
    ];

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }
}
