<?php

namespace Solt\Bonus\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Solt\Bonus\Models\BalanceSnapshot;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\CardOperation;

/**
 * Закрытие месяца: срез остатков по парам (карта, юрлицо).
 *
 * Правила:
 *  - операция относится к месяцу по дате документа (document_date);
 *  - отмена относится к месяцу, в котором её сделали (deleted_at);
 *  - операция или отмена, пришедшая после закрытия своего месяца
 *    (например, чек задним числом), попадает в первый ещё не закрытый месяц;
 *  - месяцы закрываются строго по порядку; первый срез строится по всей истории;
 *  - закрытый месяц не меняется; пересчитать можно только последний закрытый,
 *    и только по секретному ключу.
 */
class SnapshotService
{
    /**
     * Закрыть месяц. $period — любая дата внутри месяца или 'YYYY-MM'.
     *
     * @return array сводка: period, rows, cards, mismatch (расхождение с текущими остатками)
     */
    public function closeMonth(string $period): array
    {
        $start = $this->normalizePeriod($period);
        $end   = $start->copy()->endOfMonth()->endOfDay();

        if ($end->isFuture()) {
            throw new InvalidArgumentException("Месяц {$start->format('Y-m')} ещё не закончился");
        }

        $last = BalanceSnapshot::lastClosedPeriod();
        if ($last !== null) {
            $expected = Carbon::parse($last)->addMonth()->startOfMonth();
            if (!$start->equalTo($expected)) {
                throw new RuntimeException(
                    "Последний закрытый месяц — " . Carbon::parse($last)->format('Y-m')
                    . ", следующим можно закрыть только {$expected->format('Y-m')}"
                );
            }
        }

        return DB::transaction(function () use ($start, $end, $last) {
            $periodKey = $start->format('Y-m-d');

            // 1. Операции, ещё не вошедшие ни в один срез, с датой документа до конца месяца
            $ops = CardOperation::withTrashed()
                ->whereNull('closed_period')
                ->where('document_date', '<=', $end->format('Y-m-d'))
                ->selectRaw('card_id, legal_entity_id, action, SUM(amount) AS total')
                ->groupBy('card_id', 'legal_entity_id', 'action')
                ->get();

            // 2. Отмены, ещё не вошедшие ни в один срез, сделанные до конца месяца
            $cancels = CardOperation::withTrashed()
                ->whereNotNull('deleted_at')
                ->whereNull('cancel_closed_period')
                ->where('deleted_at', '<=', $end)
                ->selectRaw('card_id, legal_entity_id, action, SUM(amount) AS total')
                ->groupBy('card_id', 'legal_entity_id', 'action')
                ->get();

            // 3. Остатки предыдущего среза
            $opening = [];
            if ($last !== null) {
                foreach (BalanceSnapshot::where('period', $last)->get(['card_id', 'legal_entity_id', 'closing']) as $s) {
                    $opening[$this->key($s->card_id, $s->legal_entity_id)] = (float) $s->closing;
                }
            }

            // 4. Собираем строки
            $rows = [];
            $init = function (int $cardId, ?int $leId) use (&$rows, &$opening) {
                $k = $this->key($cardId, $leId);
                if (!isset($rows[$k])) {
                    $rows[$k] = [
                        'card_id' => $cardId, 'legal_entity_id' => $leId,
                        'opening' => $opening[$k] ?? 0.0,
                        'increased' => 0.0, 'decreased' => 0.0, 'returned_increase' => 0.0,
                        'returned_decrease' => 0.0, 'moved' => 0.0,
                        'cancelled_increase' => 0.0, 'cancelled_decrease' => 0.0, 'cancelled_other' => 0.0,
                    ];
                }
                return $k;
            };

            foreach ($ops as $o) {
                $k = $init((int) $o->card_id, $o->legal_entity_id !== null ? (int) $o->legal_entity_id : null);
                $t = (float) $o->total;
                switch ((int) $o->action) {
                    case CardOperation::$increase_action:        $rows[$k]['increased']         += $t;  break;
                    case CardOperation::$decreser_action:        $rows[$k]['decreased']         += -$t; break;
                    case CardOperation::$increase_return_action: $rows[$k]['returned_increase'] += -$t; break;
                    case CardOperation::$decreser_return_action: $rows[$k]['returned_decrease'] += $t;  break;
                    default:                                     $rows[$k]['moved']             += $t;  break;
                }
            }

            foreach ($cancels as $c) {
                $k = $init((int) $c->card_id, $c->legal_entity_id !== null ? (int) $c->legal_entity_id : null);
                $t = (float) $c->total;
                switch ((int) $c->action) {
                    case CardOperation::$increase_action: $rows[$k]['cancelled_increase'] += $t;  break;
                    case CardOperation::$decreser_action: $rows[$k]['cancelled_decrease'] += -$t; break;
                    default:                              $rows[$k]['cancelled_other']    += $t;  break;
                }
            }

            // Пары без движений в этом месяце переносятся с прежним сальдо
            foreach ($opening as $k => $sum) {
                if (!isset($rows[$k])) {
                    [$cardId, $leId] = $this->unkey($k);
                    $init($cardId, $leId);
                }
            }

            $now = now();
            $insert = [];
            foreach ($rows as $r) {
                $r['closing'] = round(
                    $r['opening'] + $r['increased'] - $r['decreased'] - $r['returned_increase'] + $r['returned_decrease'] + $r['moved']
                    - $r['cancelled_increase'] + $r['cancelled_decrease'] - $r['cancelled_other'],
                    2
                );
                $r['period'] = $periodKey;
                $r['created_at'] = $now;
                $r['updated_at'] = $now;
                $insert[] = $r;
            }
            foreach (array_chunk($insert, 500) as $chunk) {
                BalanceSnapshot::insert($chunk);
            }

            // 5. Помечаем операции и отмены как вошедшие в срез
            CardOperation::withTrashed()
                ->whereNull('closed_period')
                ->where('document_date', '<=', $end->format('Y-m-d'))
                ->update(['closed_period' => $periodKey]);

            CardOperation::withTrashed()
                ->whereNotNull('deleted_at')
                ->whereNull('cancel_closed_period')
                ->where('deleted_at', '<=', $end)
                ->update(['cancel_closed_period' => $periodKey]);

            // 6. Контроль: если после конца месяца движений не было, срез должен совпасть с текущими остатками
            $mismatch = $this->mismatchWithCurrentBalances($periodKey);

            return [
                'period'   => $start->format('Y-m'),
                'rows'     => count($insert),
                'cards'    => count(array_unique(array_column($insert, 'card_id'))),
                'mismatch' => $mismatch,
            ];
        });
    }

    /**
     * Пересчитать последний закрытый месяц. Требует секретный ключ.
     */
    public function recomputeLast(string $key): array
    {
        $expected = config('bonus.admin_key');
        if (empty($expected) || !hash_equals((string) $expected, $key)) {
            throw new RuntimeException('Неверный секретный ключ');
        }

        $last = BalanceSnapshot::lastClosedPeriod();
        if ($last === null) {
            throw new RuntimeException('Нет закрытых месяцев');
        }

        return DB::transaction(function () use ($last) {
            BalanceSnapshot::where('period', $last)->delete();
            CardOperation::withTrashed()->where('closed_period', $last)->update(['closed_period' => null]);
            CardOperation::withTrashed()->where('cancel_closed_period', $last)->update(['cancel_closed_period' => null]);
            return $this->closeMonth($last);
        });
    }

    /**
     * Расхождения между срезом и текущими остатками bonus_balances
     * с учётом операций и отмен, ещё не вошедших в срез.
     * Пусто — всё сходится.
     */
    public function mismatchWithCurrentBalances(string $periodKey): array
    {
        $snap = BalanceSnapshot::where('period', $periodKey)->get(['card_id', 'legal_entity_id', 'closing']);
        $expected = [];
        foreach ($snap as $s) {
            $expected[$this->key($s->card_id, $s->legal_entity_id)] = (float) $s->closing;
        }

        // Движения после среза
        $after = CardOperation::withTrashed()->whereNull('closed_period')
            ->selectRaw('card_id, legal_entity_id, SUM(amount) AS total')
            ->groupBy('card_id', 'legal_entity_id')->get();
        foreach ($after as $a) {
            $k = $this->key($a->card_id, $a->legal_entity_id);
            $expected[$k] = ($expected[$k] ?? 0) + (float) $a->total;
        }
        $afterCancel = CardOperation::withTrashed()->whereNotNull('deleted_at')->whereNull('cancel_closed_period')
            ->selectRaw('card_id, legal_entity_id, SUM(amount) AS total')
            ->groupBy('card_id', 'legal_entity_id')->get();
        foreach ($afterCancel as $a) {
            $k = $this->key($a->card_id, $a->legal_entity_id);
            $expected[$k] = ($expected[$k] ?? 0) - (float) $a->total;
        }

        // Текущие остатки. Переносы без юрлица в bonus_balances не различимы —
        // сравниваем только пары с юрлицом, а «без юрлица» прибавляем к карте целиком.
        $actual = [];
        foreach (BonusBalance::whereNull('deleted_at')->get(['card_id', 'legal_entity_id', 'sum']) as $b) {
            $actual[$this->key($b->card_id, $b->legal_entity_id)] = (float) $b->sum;
        }

        $byCardExpected = [];
        $byCardActual = [];
        foreach ($expected as $k => $v) { [$c] = $this->unkey($k); $byCardExpected[$c] = ($byCardExpected[$c] ?? 0) + $v; }
        foreach ($actual as $k => $v)   { [$c] = $this->unkey($k); $byCardActual[$c]   = ($byCardActual[$c] ?? 0) + $v; }

        $diff = [];
        foreach (array_unique(array_merge(array_keys($byCardExpected), array_keys($byCardActual))) as $cardId) {
            $e = round($byCardExpected[$cardId] ?? 0, 2);
            $a = round($byCardActual[$cardId] ?? 0, 2);
            if (abs($e - $a) >= 0.01) {
                $diff[] = ['card_id' => $cardId, 'snapshot' => $e, 'balance' => $a, 'diff' => round($a - $e, 2)];
            }
        }
        return $diff;
    }

    public function normalizePeriod(string $period): Carbon
    {
        try {
            return Carbon::parse(strlen($period) === 7 ? $period . '-01' : $period)->startOfMonth()->startOfDay();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Неверный период «{$period}», ожидается ГГГГ-ММ");
        }
    }

    private function key(int $cardId, ?int $leId): string
    {
        return $cardId . ':' . ($leId ?? '0');
    }

    private function unkey(string $k): array
    {
        [$c, $l] = explode(':', $k);
        return [(int) $c, $l === '0' ? null : (int) $l];
    }
}
