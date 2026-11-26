<?php

namespace Solt\Bonus\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Services\LedgerService;

/**
 * Пересчёт остатков (card_after / wallet_after) по суммам операций.
 * Применяется только когда bonus:ledger-check нашёл разрывы.
 */
class LedgerRebuildCommand extends Command
{
    protected $signature = 'bonus:ledger-rebuild {card? : id карты} {--all : Все карты с разрывами} {--force : Без вопроса}';

    protected $description = 'Пересчитать остатки в журнале операций по суммам (лечение разрывов цепочки)';

    public function handle(): int
    {
        if ($this->argument('card')) {
            $ids = [(int) $this->argument('card')];
        } elseif ($this->option('all')) {
            $ids = CardOperation::query()->distinct()->pluck('card_id')
                ->filter(fn ($id) => LedgerService::checkCard((int) $id) !== [])->values()->all();
            if (!$ids) {
                $this->info('Разрывов нет — пересчитывать нечего.');
                return self::SUCCESS;
            }
        } else {
            $this->error('Укажите id карты или --all');
            return self::FAILURE;
        }

        $this->line('Карт к пересчёту: ' . count($ids) . ' (' . implode(', ', array_slice($ids, 0, 20)) . (count($ids) > 20 ? ', …' : '') . ')');
        if (!$this->option('force') && !$this->confirm('Остатки будут перезаписаны по суммам операций. Продолжить?')) {
            return self::FAILURE;
        }

        foreach ($ids as $id) {
            $r = DB::transaction(function () use ($id) {
                Card::withTrashed()->lockForUpdate()->findOrFail($id);
                return LedgerService::rebuildCard($id);
            });
            $this->line(sprintf('card %d: изменено строк %d, баланс %.2f → %.2f', $id, $r['changed'], $r['before'], $r['after']));
        }
        $this->info('Готово.');
        return self::SUCCESS;
    }
}
