<?php
namespace Solt\Bonus\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Http\Resources\CardOperationResource;
use Solt\Bonus\Interfaces\CardOperation\CardOperationInterface;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

class CardOperationService implements CardOperationInterface{

    /**
     * @inheritcod
     */
    function get() : ResourceCollection {
        return CardOperationResource::collection( CardOperation::get() );
    }

    /**
     * @inheritcod
     */
    function getItem( Int $id ) : JsonResource {
        return new CardOperationResource( CardOperation::findOrFail( $id ) );
    }

    /**
     * Начисление, списание, возвраты.
     * @inheritcod
     */
    function store(array $data): JsonResource|Bool
    {
        return DB::transaction(function () use ($data) {
            // Блокируем строку карты: все операции по одной карте выполняются
            // последовательно, и остаток читается уже под блокировкой.
            $card = Card::findByShortCode( $data['short_code'], true );
            $amount = round((float) $data['amount'], 2);
            $offline = !empty($data['offline']);

            if( $card->getBalance() + $amount < 0 ){
                if( !$offline ){
                    return false;
                }
                // Офлайн-списание: касса уже дала скидку без связи, отказать нельзя.
                // Списываем сколько есть — баланс становится 0, запрошенная сумма сохраняется.
                $data['amount_requested'] = $amount;
                $amount = -1 * $card->getBalance();
            }

            $card_operation = LedgerService::append(
                $card,
                LegalEntity::findByInn( $data['inn'] ),
                Pharmacy::findByNumber( $data['pharmacy_number'] ),
                (int) $data['action'],
                $amount,
                $data
            );

            return new CardOperationResource( $card_operation );
        });
    }

    /**
     * Отмена операции — сторно: новая строка с обратной суммой и ссылкой
     * на исходную; исходная помечается deleted_at («погашена»).
     * @inheritcod
     */
    function delete( String $short_code, String $document_number, String $document_type, bool $offline = false ) : Bool {
        return DB::transaction(function () use ($short_code, $document_number, $document_type, $offline) {
            $card = Card::findByShortCode( $short_code, true );
            if( empty( $card ) ){
                return false;
            }
            $original = CardOperation::findByRequisites( $short_code, $document_number, $document_type );
            if( empty( $original ) || $original->action == CardOperation::$cancel_action ){
                return false;
            }
            $amount = -1 * (float) $original->amount;
            $amount_requested = null;
            if( $card->getBalance() + $amount < 0 ){
                if( !$offline ){
                    return false;
                }
                // Офлайн-отмена начисления, которое уже потратили: снимаем сколько есть, баланс 0.
                $amount_requested = $amount;
                $amount = -1 * $card->getBalance();
            }

            LedgerService::append(
                $card,
                $original->legal_entity,
                $original->pharmacy,
                CardOperation::$cancel_action,
                $amount,
                [
                    'document_date'   => now()->format('Y-m-d'),
                    'document_number' => $original->document_number,
                    'document_type'   => $original->document_type,
                    'offline'         => $offline,
                    'amount_requested' => $amount_requested,
                ],
                $original
            );

            $original->deleted_at = now();
            $original->save();

            return true;
        });
    }

    /**
     * Перенос всех кошельков карты на другую карту: по паре строк на каждое юрлицо.
     * @inheritcod
     */
    function move(String $short_code_from, String $short_code_to, String $document_number, String $document_date, String $document_type): Bool
    {
        return DB::transaction(function () use ($short_code_from, $short_code_to, $document_number, $document_date, $document_type) {
            $card_from = Card::findByShortCode( $short_code_from, true );
            $card_to = Card::findByShortCode( $short_code_to, true );

            if( $card_from->getBalance() == 0 ){
                return false;
            }

            $document = [
                'document_number' => $document_number,
                'document_date'   => $document_date,
                'document_type'   => $document_type,
            ];

            foreach( $card_from->getWallets() as $legal_entity_id => $sum ){
                if( abs($sum) < 0.005 ){
                    continue;
                }
                $legal_entity = $legal_entity_id === '' ? null : LegalEntity::find( $legal_entity_id );

                LedgerService::append( $card_to,   $legal_entity, null, CardOperation::$move_action,  $sum, $document );
                LedgerService::append( $card_from, $legal_entity, null, CardOperation::$move_action, -$sum, $document );
            }
            return true;
        });
    }
}
