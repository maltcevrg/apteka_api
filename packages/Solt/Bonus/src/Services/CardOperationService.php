<?php
namespace Solt\Bonus\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\Cast\Bool_;
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
        $card = Card::findByShortCode( $data['short_code'] );
        if( $card->getBalance() + $data['amount'] < 0 ){
            return false;
        }

        DB::beginTransaction();
        try {
            $card_operation = new CardOperation( $data );
            $card_operation->card()->associate( $card ) ;
            $card_operation->legal_entity()->associate( LegalEntity::findByInn( $data['inn'] )) ;
            $card_operation->pharmacy()->associate( Pharmacy::findByNumber( $data['pharmacy_number'] ) );
            $card_operation->save();

            $this->bonus_balance_service = new BonusBalanceService( $card_operation->card, $card_operation->legal_entity );
            $this->bonus_balance_service->changeBalance( $card_operation->amount );
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
        }

        return new CardOperationResource( $card_operation );
    }

    /**
     * @inheritcod
     */
    function delete( String $short_code, String $document_number, String $document_type ) : Bool {
        $card_operation = CardOperation::findByRequisites( $short_code, $document_number, $document_type );
        if( empty( $card_operation ) ){
            return false;
        }
        $card = Card::findByShortCode( $short_code );
        if( $card->getBalance() - $card_operation->amount < 0 ){
            return false;
        }

        DB::beginTransaction();
        try {
            $this->bonus_balance_service = new BonusBalanceService( $card_operation->card, $card_operation->legal_entity );
            $this->bonus_balance_service->changeBalance( -1*$card_operation->amount );

            $card_operation->delete();
            DB::commit();
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * @inheritcod
     */
    function move(String $short_code_from, String $short_code_to, String $document_number, String $document_date, String $document_type): Bool
    {
        $card_from = Card::findByShortCode( $short_code_from );
        $card_to = Card::findByShortCode( $short_code_to );

        $card_from_balance = $card_from->getBalance();

        if( $card_from_balance == 0 ){
            return false;
        }

        DB::beginTransaction();
        try {
            $card_operation_to = new CardOperation([
                'action'            => CardOperation::$move_action,
                'document_number'   => $document_number,
                'document_date'     => $document_date,
                'document_type'     => $document_type,
                'amount'            => $card_from_balance
            ]);
            $card_operation_to->card()->associate( $card_to ) ;
            $card_operation_to->save();

            $card_operation_from = new CardOperation([
                'action'            => CardOperation::$move_action,
                'document_number'   => $document_number,
                'document_date'     => $document_date,
                'document_type'     => $document_type,
                'amount'            => -1*$card_from_balance
            ]);
            $card_operation_from->card()->associate( $card_from ) ;
            $card_operation_from->save();


            foreach( $card_from->balances as $card_from_bonus_balance ){
                $card_to_bonus_balance_service = new BonusBalanceService( $card_to, $card_from_bonus_balance->legal_entity );
                $card_to_bonus_balance_service->changeBalance( $card_from_bonus_balance->sum );
                $card_from_bonus_balance_service = new BonusBalanceService( $card_from, $card_from_bonus_balance->legal_entity );
                $card_from_bonus_balance_service->changeBalance( -1*$card_from_bonus_balance->sum );
            }
            DB::commit();
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            return false;
        }
    }
}
