<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * До этой миграции приложение работало в UTC, а записи времени хранились без пояса.
 * После смены config('app.timezone') новые записи пишутся в местном времени,
 * а старые остались в UTC — сдвигаем их на смещение текущего пояса,
 * чтобы вся история показывалась одинаково.
 *
 * Выполняется один раз; на пустой базе ничего не делает.
 */
return new class extends Migration
{
    private array $columns = [
        'card_operations' => ['created_at', 'updated_at', 'deleted_at'],
        'admin_logs'      => ['created_at'],
        'cards'           => ['created_at', 'updated_at', 'deleted_at'],
        'pharmacies'      => ['created_at', 'updated_at', 'deleted_at'],
        'legal_entities'  => ['created_at', 'updated_at', 'deleted_at'],
        'users'           => ['created_at', 'updated_at', 'last_login_at'],
    ];

    private function offsetSeconds(): int
    {
        return (new DateTime('now', new DateTimeZone(config('app.timezone', 'UTC'))))->getOffset();
    }

    public function up(): void
    {
        $this->shift($this->offsetSeconds());
    }

    public function down(): void
    {
        $this->shift(-$this->offsetSeconds());
    }

    private function shift(int $seconds): void
    {
        if ($seconds === 0) {
            return;
        }
        $driver = DB::getDriverName();
        foreach ($this->columns as $table => $cols) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $col) {
                if (!Schema::hasColumn($table, $col)) {
                    continue;
                }
                $expr = match ($driver) {
                    'mysql', 'mariadb' => "DATE_ADD(`$col`, INTERVAL $seconds SECOND)",
                    'sqlite'           => "datetime(`$col`, '" . ($seconds >= 0 ? '+' : '') . "$seconds seconds')",
                    default            => "`$col` + INTERVAL '$seconds seconds'",
                };
                DB::table($table)->whereNotNull($col)->update([$col => DB::raw($expr)]);
            }
        }
    }
};
