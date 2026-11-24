<?php
namespace Solt\Bonus\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Solt\Bonus\Http\Resources\CardOperationResource;
use Solt\Bonus\Http\Resources\CardResource;
use Solt\Bonus\Interfaces\Card\CardInterface;
use Solt\Bonus\Interfaces\CardOperation\CardOperationInterface;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\CardOperation;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

class CardOperationService implements CardOperationInterface{

    protected BonusBalanceService $bonus_balance_service;

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
     * @inheritcod
     */
    function store(array $data): JsonResource|Bool
    {
        return DB::transaction(function () use ($data) {
            // Блокируем строку карты: все операции по одной карте выполняются
            // последовательно, и баланс проверяется уже под блокировкой.
            $card = Card::findByShortCode( $data['short_code'], true );
            if( $card->getBalance() + $data['amount'] < 0 ){
                return false;
            }

            $card_operation = new CardOperation( $data );
            $card_operation->card()->associate( $card ) ;
            $card_operation->legal_entity()->associate( LegalEntity::findByInn( $data['inn'] )) ;
            $card_operation->pharmacy()->associate( Pharmacy::findByNumber( $data['pharmacy_number'] ) );
            $card_operation->save();

            $this->bonus_balance_service = new BonusBalanceService( $card_operation->card, $card_operation->legal_entity );
            $this->bonus_balance_service->changeBalance( $card_operation->amount );

            return new CardOperationResource( $card_operation );
        });
    }

    /**
     * @inheritcod
     */
    function delete( String $short_code, String $document_number, String $document_type ) : Bool {
        return DB::transaction(function () use ($short_code, $document_number, $document_type) {
            $card = Card::findByShortCode( $short_code, true );
            if( empty( $card ) ){
                return false;
            }
            $card_operation = CardOperation::findByRequisites( $short_code, $document_number, $document_type );
            if( empty( $card_operation ) ){
                return false;
            }
            if( $card->getBalance() - $card_operation->amount < 0 ){
                return false;
            }

            $this->bonus_balance_service = new BonusBalanceService( $card_operation->card, $card_operation->legal_entity );
            $this->bonus_balance_service->changeBalance( -1*$card_operation->amount );

            $card_operation->delete();
            return true;
        });
    }

    /**
     * @inheritcod
     */
    function move(String $short_code_from, String $short_code_to, String $document_number, String $document_date, String $document_type): Bool
    {
        return DB::transaction(function () use ($short_code_from, $short_code_to, $document_number, $document_date, $document_type) {
            $card_from = Card::findByShortCode( $short_code_from, true );
            $card_to = Card::findByShortCode( $short_code_to, true );

            $card_from_balance = $card_from->getBalance();

            if( $card_from_balance == 0 ){
                return false;
            }

            // Переносим каждый кошелёк (юрлицо) отдельно и пишем по паре операций
            // на кошелёк, чтобы остатки по юрлицам сходились с журналом.
            foreach( $card_from->balances()->get() as $card_from_bonus_balance ){
                $sum = (float) $card_from_bonus_balance->sum;
                if( abs($sum) < 0.005 ){
                    continue;
                }
                $legal_entity = $card_from_bonus_balance->legal_entity;

                $card_operation_to = new CardOperation([
                    'action'            => CardOperation::$move_action,
                    'document_number'   => $document_number,
                    'document_date'     => $document_date,
                    'document_type'     => $document_type,
                    'amount'            => $sum
                ]);
                $card_operation_to->card()->associate( $card_to );
                $card_operation_to->legal_entity()->associate( $legal_entity );
                $card_operation_to->save();

                $card_operation_from = new CardOperation([
                    'action'            => CardOperation::$move_action,
                    'document_number'   => $document_number,
                    'document_date'     => $document_date,
                    'document_type'     => $document_type,
                    'amount'            => -1*$sum
                ]);
                $card_operation_from->card()->associate( $card_from );
                $card_operation_from->legal_entity()->associate( $legal_entity );
                $card_operation_from->save();

                $card_to_bonus_balance_service = new BonusBalanceService( $card_to, $legal_entity );
                $card_to_bonus_balance_service->changeBalance( $sum );
                $card_from_bonus_balance_service = new BonusBalanceService( $card_from, $legal_entity );
                $card_from_bonus_balance_service->changeBalance( -1*$sum );
            }
            return true;
        });
    }
}
