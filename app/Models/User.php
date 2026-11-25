<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Пользователь админ-панели. Вход по связке login + password.
 *
 * Роли:
 *   admin  — всё, включая управление пользователями;
 *   editor — изменение данных (аптеки, юрлица, загрузка из 1С) + отчёты;
 *   user   — только просмотр и отчёты.
 *
 * password = NULL — пользователь создан администратором, но ещё не входил:
 * при первом входе по логину он задаёт пароль сам.
 */
class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMIN  = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_USER   = 'user';

    public const ROLES = [
        self::ROLE_ADMIN  => 'Администратор',
        self::ROLE_EDITOR => 'Редактор',
        self::ROLE_USER   => 'Пользователь',
    ];

    protected $fillable = ['name', 'login', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password'      => 'hashed',
        'last_login_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Может менять данные (аптеки, юрлица, загрузка). */
    public function canEdit(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR], true);
    }

    public function roleName(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** Пароль ещё не задан — ждёт первого входа. */
    public function needsPassword(): bool
    {
        return $this->password === null || $this->password === '';
    }

    /**
     * Правила для пароля: 8–64 символа, латинские буквы, цифры и обычные знаки
     * препинания, обязательно буква и цифра. Без пробелов, кириллицы и спецсимволов,
     * которые ломают адресную строку, конфиги или консоль.
     */
    public static function passwordRules(): array
    {
        return [
            'required', 'string', 'min:8', 'max:64', 'confirmed',
            'regex:/^[A-Za-z0-9!@#$%^*()_+\-=.,:;?\[\]{}~]+$/',
            'regex:/[A-Za-z]/',
            'regex:/[0-9]/',
        ];
    }

    public static function passwordRulesMessage(): string
    {
        return 'Пароль: от 8 до 64 символов, латинские буквы и цифры (обязательно и те, и другие), допустимы знаки ! @ # $ % ^ * ( ) _ + - = . , : ; ? [ ] { } ~. Без пробелов и кириллицы.';
    }
}
