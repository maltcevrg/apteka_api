<?php

namespace Solt\Bonus\Console;

use Illuminate\Console\Command;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Services\LedgerService;

class LedgerCheckCommand extends Command
{
    protected $signature = 'bonus:ledger-check {--card= : Проверить только карту с этим id}';

    protected $description = 'Проверить цепочку остатков в журнале операций (card_after / wallet_after)';

    public function handle(): int
    {
        $query = CardOperation::query()->distinct();
        if ($this->option('card')) {
            $query->where('card_id', (int) $this->option('card'));
        }
        $cardIds = $query->pluck('card_id');

        $broken = 0;
        $rows = [];
        foreach ($cardIds as $cardId) {
            $errors = LedgerService::checkCard((int) $cardId);
            if ($errors) {
                $broken++;
                foreach (array_slice($errors, 0, 5) as $e) {
                    $rows[] = [$cardId, $e['id'], $e['field'], $e['expected'], $e['actual']];
                }
            }
        }

        $this->line("Карт с операциями: {$cardIds->count()}, с разрывами: {$broken}");
        if ($broken === 0) {
            $this->info('Цепочка остатков целая.');
            return self::SUCCESS;
        }

        $this->warn('Разрывы (первые 5 на карту):');
        $this->table(['card_id', 'operation_id', 'поле', 'должно быть', 'записано'], $rows);
        return self::FAILURE;
    }
}
