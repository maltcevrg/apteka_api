<?php

namespace Solt\Bonus\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Solt\Bonus\Services\SnapshotService;

class CloseMonthCommand extends Command
{
    protected $signature = 'bonus:close-month
        {period? : Месяц в формате ГГГГ-ММ. По умолчанию — предыдущий месяц}
        {--recompute : Пересчитать последний закрытый месяц (нужен --key)}
        {--key= : Секретный ключ администратора (BONUS_ADMIN_KEY)}';

    protected $description = 'Закрыть месяц: сохранить срез остатков по картам и юрлицам';

    public function handle(SnapshotService $service): int
    {
        try {
            if ($this->option('recompute')) {
                $result = $service->recomputeLast((string) $this->option('key'));
                $this->info("Месяц {$result['period']} пересчитан.");
            } else {
                $period = $this->argument('period') ?: Carbon::now()->subMonthNoOverflow()->format('Y-m');
                $result = $service->closeMonth($period);
                $this->info("Месяц {$result['period']} закрыт.");
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line("Строк среза: {$result['rows']}, карт: {$result['cards']}");

        if (empty($result['mismatch'])) {
            $this->info('Срез сходится с текущими остатками.');
        } else {
            $this->warn('Срез НЕ сходится с текущими остатками по картам:');
            $this->table(['card_id', 'по срезу', 'в bonus_balances', 'разница'], array_map(
                fn ($m) => [$m['card_id'], $m['snapshot'], $m['balance'], $m['diff']],
                $result['mismatch']
            ));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
