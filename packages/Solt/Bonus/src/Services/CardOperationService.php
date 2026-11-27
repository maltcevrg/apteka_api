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
            if( empty( $card ) ){
                return false; // карта удалена или аннулирована
            }
            $pharmacy = Pharmacy::findByNumber( $data['pharmacy_number'] );

            // Повтор того же документа (касса не дождалась ответа и прислала снова):
            // возвращаем уже записанную операцию, ничего не дублируя.
            $dup = CardOperation::findDuplicate( $pharmacy?->id, (string) $data['document_number'], (string) $data['document_type'], (int) $data['action'] );
            if( $dup ){
                if( (int) $dup->card_id !== (int) $card->id ){
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'document_number' => "Документ {$data['document_type']} №{$data['document_number']} аптеки {$data['pharmacy_number']} уже проведён по другой карте",
                    ]);
                }
                $dup->setAttribute('duplicate', true);
                return new CardOperationResource( $dup );
            }

            // Баланс может уходить в минус: ограничений по остатку нет,
            // списывается ровно запрошенная сумма. Флаг offline — только пометка.
            $amount = round((float) $data['amount'], 2);
            unset($data['amount_requested']);

            $card_operation = LedgerService::append(
                $card,
                LegalEntity::findByInn( $data['inn'] ),
                $pharmacy,
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
    function delete( String $short_code, String $document_number, String $document_type, bool $offline = false, $pharmacy_number = null ) : Bool {
        return DB::transaction(function () use ($short_code, $document_number, $document_type, $offline, $pharmacy_number) {
            $card = Card::findByShortCode( $short_code, true );
            if( empty( $card ) ){
                return false;
            }
            $original = CardOperation::findByRequisites( $short_code, $document_number, $document_type, $pharmacy_number );
            if( empty( $original ) ){
                // Повтор отмены: документ уже погашен сторно — отвечаем успехом, второй раз не сторнируем.
                $gone = CardOperation::findByRequisites( $short_code, $document_number, $document_type, $pharmacy_number, true );
                return $gone && $gone->deleted_at && CardOperation::where('parent_id', $gone->id)->exists();
            }
            // Сторно всегда на полную сумму — баланс может стать отрицательным.
            $amount = -1 * (float) $original->amount;

            LedgerService::append(
                $card,
                $original->legal_entity,
                $original->pharmacy,
                CardOperation::$cancel_action,
                $amount,
                [
                    'document_date'   => now()->format('Y-m-d'),
                    'document_time'   => now()->format('H:i:s'),
                    'document_number' => $original->document_number,
                    'document_type'   => $original->document_type,
                    'offline'         => $offline,
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

            if( empty( $card_from ) || empty( $card_to ) || abs( $card_from->getBalance() ) < 0.005 ){
                return false;
            }

            $document = [
                'document_number' => $document_number,
                'document_date'   => $document_date,
                'document_time'   => now()->format('H:i:s'),
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
