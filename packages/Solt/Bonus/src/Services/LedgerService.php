<?php

namespace Solt\Bonus\Services;

use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

/**
 * Журнал с нарастающим итогом — единственная точка записи операций.
 *
 * Каждая строка card_operations хранит остаток после себя:
 *  - wallet_after — по кошельку (карта + юрлицо),
 *  - card_after   — по карте целиком.
 * Порядок цепочки — ХРОНОЛОГИЧЕСКИЙ: по дате документа, внутри дня — по
 * порядку записи (id). Текущий баланс — последняя строка в этом порядке.
 * Если пришёл документ задним числом (офлайн-чек за прошлые дни), он
 * встаёт на своё место по дате, а остатки всех более поздних строк карты
 * пересчитываются (rebuildCard) — история читается как выписка.
 *
 * Вызывать только внутри транзакции с заблокированной строкой карты
 * (Card::findByShortCode($code, true)), иначе две параллельные операции
 * прочитают один и тот же «предыдущий» остаток.
 */
class LedgerService
{
    /**
     * Остаток карты.
     */
    public static function cardBalance(Card|int $card): float
    {
        $cardId = $card instanceof Card ? $card->id : $card;
        $last = self::chronological(CardOperation::where('card_id', $cardId), true)->value('card_after');
        return round((float) ($last ?? 0), 2);
    }

    /** Хронологический порядок: дата документа, время (без времени = начало дня), затем порядок записи. */
    public static function chronological($query, bool $desc = false)
    {
        $dir = $desc ? 'desc' : 'asc';
        return $query->orderBy('document_date', $dir)
            ->orderByRaw("COALESCE(document_time, '00:00:00') {$dir}")
            ->orderBy('id', $dir);
    }

    /**
     * Есть ли у карты строки, стоящие в хронологии ПОЗЖЕ указанных даты/времени
     * (т.е. новый документ — задним числом).
     */
    public static function isBackdated(int $cardId, string $date, ?string $time, ?int $exceptId = null): bool
    {
        $date = substr($date, 0, 10);
        $time = $time ? substr($time, 0, 8) : '00:00:00';
        $q = CardOperation::where('card_id', $cardId)
            ->where(function ($w) use ($date, $time) {
                $w->whereDate('document_date', '>', $date)
                  ->orWhere(function ($w2) use ($date, $time) {
                      $w2->whereDate('document_date', $date)
                         ->whereRaw("COALESCE(document_time, '00:00:00') > ?", [$time]);
                  });
            });
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }
        return $q->exists();
    }

    /**
     * Остаток кошелька карты по юрлицу.
     */
    public static function walletBalance(Card|int $card, ?int $legalEntityId): float
    {
        $cardId = $card instanceof Card ? $card->id : $card;
        $query = self::chronological(CardOperation::where('card_id', $cardId), true);
        $legalEntityId === null
            ? $query->whereNull('legal_entity_id')
            : $query->where('legal_entity_id', $legalEntityId);
        return round((float) ($query->value('wallet_after') ?? 0), 2);
    }

    /**
     * Все кошельки карты: [legal_entity_id => остаток]. Нулевые не отбрасываются.
     *
     * @return array<int|string, float>
     */
    public static function wallets(Card|int $card): array
    {
        $cardId = $card instanceof Card ? $card->id : $card;
        // последняя (хронологически) строка каждого кошелька
        $result = [];
        $rows = self::chronological(CardOperation::where('card_id', $cardId), true)
            ->get(['legal_entity_id', 'wallet_after']);
        foreach ($rows as $row) {
            $key = $row->legal_entity_id ?? '';
            if (!array_key_exists($key, $result)) {
                $result[$key] = round((float) $row->wallet_after, 2);
            }
        }
        return $result;
    }

    /**
     * Записать операцию с нарастающим итогом.
     *
     * @param array $document  document_date, document_number, document_type,
     *                         offline (bool), amount_requested (если списано частично)
     */
    public static function append(
        Card $card,
        ?LegalEntity $legalEntity,
        ?Pharmacy $pharmacy,
        int $action,
        float $amount,
        array $document,
        ?CardOperation $parent = null
    ): CardOperation {
        $legalEntityId = $legalEntity?->id;

        $operation = new CardOperation([
            'action'          => $action,
            'document_date'   => substr((string) $document['document_date'], 0, 10),
            'document_time'   => $document['document_time'] ?? null,
            'document_number' => $document['document_number'],
            'document_type'   => $document['document_type'],
            'amount'          => round($amount, 2),
            'offline'         => !empty($document['offline']),
            'amount_requested' => isset($document['amount_requested']) ? round((float) $document['amount_requested'], 2) : null,
            'user_id'         => $document['user_id'] ?? null,
            'comment'         => $document['comment'] ?? null,
        ]);
        $operation->card()->associate($card);
        if ($legalEntity !== null) {
            $operation->legal_entity()->associate($legalEntity);
        }
        if ($pharmacy !== null) {
            $operation->pharmacy()->associate($pharmacy);
        }
        if ($parent !== null) {
            $operation->parent()->associate($parent);
        }

        // Документ задним числом? (есть строки карты с более поздней датой)
        $backdated = self::isBackdated($card->id, (string) $document['document_date'], $document['document_time'] ?? null);

        if ($backdated) {
            // Встаёт на своё место по дате; остатки этой и всех последующих строк
            // пересчитывает хук CardOperation::created (rebuildCard).
            $operation->wallet_after = 0;
            $operation->card_after   = 0;
            $operation->save();
        } else {
            $operation->wallet_after = round(self::walletBalance($card, $legalEntityId) + $amount, 2);
            $operation->card_after   = round(self::cardBalance($card) + $amount, 2);
            $operation->save();
        }

        // Юрлицо-владелец карты — по первой операции с юрлицом
        if ($card->legal_entity_id === null && $legalEntityId !== null) {
            $card->legal_entity_id = $legalEntityId;
            $card->save();
        }

        return $operation;
    }

    /**
     * Проверка цепочки остатков по карте.
     * Возвращает список разрывов: ['id' => …, 'field' => 'card_after'|'wallet_after', 'expected' => …, 'actual' => …].
     */
    public static function checkCard(int $cardId): array
    {
        $errors = [];
        $card = 0.0;
        $wallets = [];

        $rows = self::chronological(CardOperation::where('card_id', $cardId))->cursor();
        {
            {
                foreach ($rows as $op) {
                    $key = $op->legal_entity_id ?? '';
                    $card = round($card + (float) $op->amount, 2);
                    $wallets[$key] = round(($wallets[$key] ?? 0) + (float) $op->amount, 2);

                    if (abs($card - (float) $op->card_after) >= 0.01) {
                        $errors[] = ['id' => $op->id, 'field' => 'card_after', 'expected' => $card, 'actual' => (float) $op->card_after];
                    }
                    if (abs($wallets[$key] - (float) $op->wallet_after) >= 0.01) {
                        $errors[] = ['id' => $op->id, 'field' => 'wallet_after', 'expected' => $wallets[$key], 'actual' => (float) $op->wallet_after];
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Пересчёт остатков карты: amount каждой строки считается истиной,
     * card_after / wallet_after перезаписываются нарастающим итогом
     * в хронологическом порядке (дата документа, затем id).
     * Вызывать внутри транзакции под блокировкой карты.
     *
     * @return array{changed:int, before:float, after:float, rows:array} rows — первые 20 изменённых строк (id, поле, было, стало)
     */
    public static function rebuildCard(int $cardId): array
    {
        $card = 0.0;
        $wallets = [];
        $changed = 0;
        $rows = [];
        $before = 0.0;

        $before = self::cardBalance($cardId);

        $ops = self::chronological(CardOperation::where('card_id', $cardId))->lockForUpdate()->cursor();
        {
            {
                foreach ($ops as $op) {
                    $key = $op->legal_entity_id ?? '';
                    $card = round($card + (float) $op->amount, 2);
                    $wallets[$key] = round(($wallets[$key] ?? 0) + (float) $op->amount, 2);

                    $dirty = [];
                    if (abs($card - (float) $op->card_after) >= 0.01) {
                        $dirty['card_after'] = $card;
                        if (count($rows) < 20) $rows[] = ['id' => $op->id, 'field' => 'card_after', 'old' => (float) $op->card_after, 'new' => $card];
                    }
                    if (abs($wallets[$key] - (float) $op->wallet_after) >= 0.01) {
                        $dirty['wallet_after'] = $wallets[$key];
                        if (count($rows) < 20) $rows[] = ['id' => $op->id, 'field' => 'wallet_after', 'old' => (float) $op->wallet_after, 'new' => $wallets[$key]];
                    }
                    if ($dirty) {
                        // без touch updated_at и без событий — только два поля
                        CardOperation::whereKey($op->id)->update($dirty);
                        $changed++;
                    }
                }
            }
        }

        return ['changed' => $changed, 'before' => $before, 'after' => $card, 'rows' => $rows];
    }
}
