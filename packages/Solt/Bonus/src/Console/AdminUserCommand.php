<?php

namespace Solt\Bonus\Console;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Создать администратора админ-панели или сбросить ему пароль.
 *   php artisan bonus:admin admin            — создать (или сбросить пароль): пароль задаётся при первом входе
 *   php artisan bonus:admin admin "пароль"   — сразу с паролем
 */
class AdminUserCommand extends Command
{
    protected $signature = 'bonus:admin {login : Логин} {password? : Пароль; если не указан — задаётся при первом входе}';

    protected $description = 'Создать администратора админ-панели / сбросить пароль';

    public function handle(): int
    {
        $login = trim($this->argument('login'));
        $password = $this->argument('password');

        if ($login === '' || !preg_match('/^[a-zA-Z0-9_.@-]{2,64}$/', $login)) {
            $this->error('Логин: латинские буквы, цифры, . _ @ -, от 2 до 64 символов');
            return self::FAILURE;
        }
        if ($password !== null && $password !== '') {
            $v = validator(['password' => $password, 'password_confirmation' => $password], ['password' => User::passwordRules()]);
            if ($v->fails()) {
                $this->error(User::passwordRulesMessage());
                return self::FAILURE;
            }
        } else {
            $password = null;
        }

        $user = User::firstOrNew(['login' => $login]);
        $isNew = !$user->exists;
        $user->name = $user->name ?: $login;
        $user->role = User::ROLE_ADMIN;
        $user->password = $password; // null → задаст при первом входе
        $user->remember_token = null;
        $user->save();

        $this->info(($isNew ? 'Создан администратор' : 'Обновлён администратор') . " «{$login}». "
            . ($password === null ? 'Пароль будет задан при первом входе: /admin/login' : 'Вход: /admin/login'));
        return self::SUCCESS;
    }
}
