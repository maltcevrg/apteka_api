<?php

namespace Solt\Bonus\Console;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Создать или обновить пользователя админ-панели.
 *   php artisan bonus:admin admin "пароль"
 */
class AdminUserCommand extends Command
{
    protected $signature = 'bonus:admin {login : Логин} {password : Пароль (не короче 8 символов)}';

    protected $description = 'Создать/обновить пользователя админ-панели (логин + пароль)';

    public function handle(): int
    {
        $login = trim($this->argument('login'));
        $password = (string) $this->argument('password');

        if ($login === '' || !preg_match('/^[a-zA-Z0-9_.@-]{2,64}$/', $login)) {
            $this->error('Логин: латинские буквы, цифры, . _ @ -, от 2 до 64 символов');
            return self::FAILURE;
        }
        if (mb_strlen($password) < 8) {
            $this->error('Пароль не короче 8 символов');
            return self::FAILURE;
        }

        $user = User::firstOrNew(['login' => $login]);
        $isNew = !$user->exists;
        $user->name = $user->name ?: $login;
        $user->password = $password; // cast 'hashed'
        $user->save();

        $this->info(($isNew ? 'Создан' : 'Обновлён') . " пользователь «{$login}». Вход: /admin/login");
        return self::SUCCESS;
    }
}
