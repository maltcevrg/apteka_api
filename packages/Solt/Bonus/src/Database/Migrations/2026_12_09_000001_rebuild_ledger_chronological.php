<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Services\LedgerService;

/**
 * Разовый пересчёт остатков после перехода на хронологический порядок цепочки
 * (дата + время документа вместо порядка записи). Суммы операций не меняются,
 * итоговые балансы карт не меняются — выравниваются только промежуточные
 * card_after / wallet_after у карт, где документы задним числом были записаны
 * по старым правилам.
 */
return new class extends Migration
{
    public function up(): void
    {
        @set_time_limit(0);
        $fixed = 0;
        CardOperation::query()->distinct()->orderBy('card_id')->pluck('card_id')
            ->chunk(500)
            ->each(function ($ids) use (&$fixed) {
                foreach ($ids as $id) {
                    if (LedgerService::checkCard((int) $id) === []) {
                        continue;
                    }
                    DB::transaction(fn () => LedgerService::rebuildCard((int) $id));
                    $fixed++;
                }
            });
        if ($fixed) {
            echo "Пересчитаны остатки по {$fixed} карт(ам) под хронологический порядок.\n";
        }
    }

    public function down(): void
    {
        // пересчёт необратим и не нужен: суммы операций не менялись
    }
};
