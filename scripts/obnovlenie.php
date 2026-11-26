<?php
/**
 * Обновление через браузер (когда нет SSH).
 *
 * Как пользоваться:
 *  1. В .env должна быть строка BONUS_ADMIN_KEY=ваш_секрет.
 *  2. Скопировать этот файл в public/obnovlenie.php (менеджер файлов ISPmanager).
 *  3. Открыть в браузере:
 *       https://lek-torg.ru/obnovlenie.php?key=ваш_секрет
 *     — выполнит миграции и пересоберёт кэши.
 *       https://lek-torg.ru/obnovlenie.php?key=ваш_секрет&check=1
 *     — только проверка цепочки остатков в журнале (bonus:ledger-check).
 *       https://lek-torg.ru/obnovlenie.php?key=ваш_секрет&fresh=1
 *     — ПЕРЕСОЗДАТЬ базу с нуля (migrate:fresh) и создать клиента Passport.
 *       Работает только если в журнале нет ни одной операции;
 *       чтобы стереть базу с тестовыми операциями — добавить &force=1.
 *       https://lek-torg.ru/obnovlenie.php?key=ваш_секрет&import=1
 *     — загрузить справочники и карты с остатками из database/import (bonus:import).
 *       Можно сочетать: &fresh=1&force=1&import=1 — чистая база с тестовыми данными.
 *       https://lek-torg.ru/obnovlenie.php?key=ваш_секрет&admin=логин
 *     — создать администратора админ-панели (или сбросить ему пароль):
 *       пароль он задаст сам при первом входе на /admin/login.
 *       Можно сразу с паролем: &admin=логин&password=пароль (латиница и цифры, от 8 символов).
 *       https://lek-torg.ru/obnovlenie.php?key=ваш_секрет&repair=1
 *     — восстановить таблицу migrations, если её очистили, а остальные таблицы целы
 *       («Table 'users' already exists»): миграции, чьи таблицы/колонки уже есть,
 *       помечаются выполненными, недостающие — выполняются.
 *       https://lek-torg.ru/obnovlenie.php?key=ваш_секрет&schedule=1
 *     — только запуск планировщика (php artisan schedule:run).
 *  4. УДАЛИТЬ public/obnovlenie.php после использования.
 */

declare(strict_types=1);

@set_time_limit(600);
@ini_set('memory_limit', '512M');
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);

// --- 1. Проверка ключа: читаем .env напрямую, кэш настроек может быть устаревшим
$envFile = $root . '/.env';
if (!is_file($envFile)) {
    http_response_code(500);
    exit("Нет файла .env\n");
}
$expected = null;
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^\s*BONUS_ADMIN_KEY\s*=\s*"?([^"\r\n]*)"?\s*$/', $line, $m)) {
        $expected = trim($m[1]);
    }
}
if ($expected === null || $expected === '') {
    http_response_code(500);
    exit("В .env нет BONUS_ADMIN_KEY. Добавьте строку BONUS_ADMIN_KEY=длинный_секрет и повторите.\n");
}
$key = (string) ($_GET['key'] ?? '');
if ($key === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    exit("Неверный ключ. Откройте адрес с параметром ?key=значение BONUS_ADMIN_KEY из .env\n");
}

// --- 2. Запуск Laravel в режиме консоли
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ok = true;
$run = function (string $command, array $params = []) use ($kernel, &$ok): void {
    echo "==> php artisan {$command}" . ($params ? ' ' . implode(' ', array_map(
        fn ($k, $v) => is_bool($v) ? $k : (is_int($k) ? $v : "{$k}={$v}"),
        array_keys($params), $params
    )) : '') . "\n";
    try {
        $code = $kernel->call($command, $params);
        echo $kernel->output();
        echo $code === 0 ? "OK\n\n" : "ЗАВЕРШИЛОСЬ С КОДОМ {$code}\n\n";
        if ($code !== 0) {
            $ok = false;
        }
    } catch (Throwable $e) {
        $ok = false;
        echo "ОШИБКА: " . get_class($e) . ": " . $e->getMessage() . "\n"
            . $e->getFile() . ':' . $e->getLine() . "\n\n";
    }
    flush();
};

echo "PHP " . PHP_VERSION . ", Laravel " . $app->version() . "\n";
echo "Папка: {$root}\n\n";

if (!empty($_GET['schedule'])) {
    $run('schedule:run');
} elseif (!empty($_GET['check'])) {
    $run('bonus:ledger-check');
} elseif (!empty($_GET['fresh'])) {
    $hasOps = false;
    try {
        $hasOps = Illuminate\Support\Facades\Schema::hasTable('card_operations')
            && Illuminate\Support\Facades\DB::table('card_operations')->exists();
    } catch (Throwable $e) {
        // таблиц ещё нет — база пустая
    }
    if ($hasOps && empty($_GET['force'])) {
        $ok = false;
        $count = Illuminate\Support\Facades\DB::table('card_operations')->count();
        echo "ОТКАЗ: в журнале уже есть операции ({$count} шт.), migrate:fresh запрещён.\n"
            . "Если это тестовые данные и их можно стереть — повторите с параметром &force=1\n";
    } else {
        if ($hasOps) {
            echo "ВНИМАНИЕ: force=1 — все таблицы, включая операции, будут удалены.\n\n";
        }
        $run('migrate:fresh', ['--force' => true]);
        if (!is_file($root . '/storage/oauth-private.key')) {
            $run('passport:keys', ['--no-interaction' => true]);
        }
        // Команды Passport регистрируются только в консоли, поэтому клиента создаём напрямую
        echo "==> создание клиента Passport (client_credentials)\n";
        try {
            $client = $app->make(Laravel\Passport\ClientRepository::class)
                ->createClientCredentialsGrantClient('Кассы');
            $secret = $client->plainSecret ?? $client->secret;
            echo "Client ID:     {$client->getKey()}\n";
            echo "Client Secret: {$secret}\n";
            echo "СОХРАНИТЕ СЕКРЕТ — он больше нигде не показывается. Пропишите в 1С.\n\n";
        } catch (Throwable $e) {
            $ok = false;
            echo "ОШИБКА создания клиента: " . $e->getMessage() . "\n\n";
        }
        $run('config:cache');
        $run('route:cache');
        $run('event:cache');
        if (!empty($_GET['import'])) {
            $run('bonus:import');
        }
    }
} elseif (!empty($_GET['repair'])) {
    // Таблица migrations пуста/неполна, а структура на месте: помечаем выполненными
    // те миграции, чей результат (таблица или колонка) уже есть в базе.
    echo "==> восстановление таблицы migrations\n";
    $schema = Illuminate\Support\Facades\Schema::class;
    $db = Illuminate\Support\Facades\DB::class;
    if (!$schema::hasTable('migrations')) {
        $run('migrate:install');
    }
    // имя миграции => [таблица, колонка|null]: признак, что миграция уже применена
    $markers = [
        '2014_10_12_000000_create_users_table'                         => ['users', null],
        '2014_10_12_100000_create_password_resets_table'               => ['password_resets', null],
        '2016_06_01_000001_create_oauth_auth_codes_table'              => ['oauth_auth_codes', null],
        '2016_06_01_000002_create_oauth_access_tokens_table'           => ['oauth_access_tokens', null],
        '2016_06_01_000003_create_oauth_refresh_tokens_table'          => ['oauth_refresh_tokens', null],
        '2016_06_01_000004_create_oauth_clients_table'                 => ['oauth_clients', null],
        '2019_08_19_000000_create_failed_jobs_table'                   => ['failed_jobs', null],
        '2019_12_14_000001_create_personal_access_tokens_table'        => ['personal_access_tokens', null],
        '2024_06_01_000001_create_oauth_device_codes_table'            => ['oauth_device_codes', null],
        '2022_08_02_170800_create_cards_table'                         => ['cards', null],
        '2022_08_02_170836_create_legal_entities_table'                => ['legal_entities', null],
        '2022_08_02_170929_create_pharmacies_table'                    => ['pharmacies', null],
        '2022_08_02_170951_create_card_operations_table'               => ['card_operations', null],
        '2022_08_02_171032_create_bonus_balances_table'                => ['bonus_balances', null],
        '2022_09_07_094441_update_legal_entities_table_drop_unique_index' => ['legal_entities', null],
        '2026_11_12_000001_add_indexes_to_card_operations_and_cards'   => ['card_operations', 'amount'],
        '2026_11_26_000001_drop_balance_snapshots'                     => ['card_operations', 'amount'],
        '2026_12_01_000001_ledger_columns'                             => ['card_operations', 'card_after'],
        '2026_12_02_000001_cards_birthdate_nullable'                   => ['card_operations', 'card_after'],
        '2026_12_03_000001_card_operations_offline'                    => ['card_operations', 'offline'],
        '2026_12_04_000001_users_login'                                => ['users', 'login'],
        '2026_12_05_000001_users_roles'                                => ['users', 'role'],
        '2026_12_06_000001_admin_logs_and_operation_author'            => ['admin_logs', null],
    ];
    $done = $db::table('migrations')->pluck('migration')->all();
    $batch = (int) $db::table('migrations')->max('batch') + 1;
    $marked = 0;
    foreach ($markers as $name => [$table, $column]) {
        if (in_array($name, $done, true)) {
            continue;
        }
        $exists = $column === null ? $schema::hasTable($table) : $schema::hasColumn($table, $column);
        if ($exists) {
            $db::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
            echo "  отмечена как выполненная: {$name}\n";
            $marked++;
        } else {
            echo "  будет выполнена:          {$name}\n";
        }
    }
    echo "Помечено: {$marked}\n\n";
    $run('migrate', ['--force' => true]);
    $run('config:cache');
    $run('route:cache');
    $run('event:cache');
    $run('view:clear');
} elseif (!empty($_GET['import'])) {
    $run('bonus:import');
} elseif (!empty($_GET['admin'])) {
    $run('migrate', ['--force' => true]);
    $params = ['login' => (string) $_GET['admin']];
    if (!empty($_GET['password'])) {
        $params['password'] = (string) $_GET['password'];
    }
    $run('bonus:admin', $params);
} else {
    $run('migrate', ['--force' => true]);
    $run('config:cache');
    $run('route:cache');
    $run('event:cache');
    $run('view:clear');
}

// Сброс OPcache, чтобы новый код и кэши подхватились сразу
if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "OPcache сброшен.\n";
}

echo $ok ? "\nГОТОВО. Не забудьте удалить public/obnovlenie.php\n"
         : "\nБЫЛИ ОШИБКИ — см. выше. Файл public/obnovlenie.php удалите после разбора.\n";
