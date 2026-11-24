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
} else {
    $run('migrate', ['--force' => true]);
    $run('config:cache');
    $run('route:cache');
    $run('event:cache');
}

// Сброс OPcache, чтобы новый код и кэши подхватились сразу
if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "OPcache сброшен.\n";
}

echo $ok ? "\nГОТОВО. Не забудьте удалить public/obnovlenie.php\n"
         : "\nБЫЛИ ОШИБКИ — см. выше. Файл public/obnovlenie.php удалите после разбора.\n";
