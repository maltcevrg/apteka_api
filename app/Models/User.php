<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Пользователь админ-панели. Вход по связке login + password.
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'login', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
    ];
}
