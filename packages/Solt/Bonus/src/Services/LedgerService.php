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
 * Текущий баланс — последняя строка (по id). Порядок — порядок записи,
 * а не дата документа: чек задним числом не требует пересчёта.
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
        $last = CardOperation::where('card_id', $cardId)->orderByDesc('id')->value('card_after');
        return round((float) ($last ?? 0), 2);
    }

    /**
     * Остаток кошелька карты по юрлицу.
     */
    public static function walletBalance(Card|int $card, ?int $legalEntityId): float
    {
        $cardId = $card instanceof Card ? $card->id : $card;
        $query = CardOperation::where('card_id', $cardId)->orderByDesc('id');
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
        $lastIds = CardOperation::where('card_id', $cardId)
            ->selectRaw('MAX(id) AS id')
            ->groupBy('legal_entity_id');

        $rows = CardOperation::whereIn('id', $lastIds)->get(['legal_entity_id', 'wallet_after']);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->legal_entity_id ?? ''] = round((float) $row->wallet_after, 2);
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
            'document_date'   => $document['document_date'],
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

        $operation->wallet_after = round(self::walletBalance($card, $legalEntityId) + $amount, 2);
        $operation->card_after   = round(self::cardBalance($card) + $amount, 2);
        $operation->save();

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

        CardOperation::where('card_id', $cardId)->orderBy('id')
            ->chunk(1000, function ($rows) use (&$errors, &$card, &$wallets) {
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
            });

        return $errors;
    }

    /**
     * Пересчёт остатков карты: amount каждой строки считается истиной,
     * card_after / wallet_after перезаписываются нарастающим итогом в порядке id.
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

        $last = CardOperation::where('card_id', $cardId)->orderByDesc('id')->first();
        if ($last) {
            $before = (float) $last->card_after;
        }

        CardOperation::where('card_id', $cardId)->orderBy('id')->lockForUpdate()
            ->chunkById(1000, function ($ops) use (&$card, &$wallets, &$changed, &$rows) {
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
            });

        return ['changed' => $changed, 'before' => $before, 'after' => $card, 'rows' => $rows];
    }
}
