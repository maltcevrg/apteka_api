<?php
namespace Solt\Bonus\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Solt\Bonus\Http\Resources\BonusBalanceResource;
use Solt\Bonus\Interfaces\BonusBalance\BonusBalanceInterface;
use Solt\Bonus\Models\BonusBalance;
use Solt\Bonus\Models\Card;
use Solt\Bonus\Models\LegalEntity;

class BonusBalanceService implements BonusBalanceInterface{


    protected Card $card;
    protected LegalEntity $legal_entity;

    public function __construct(Card $card, LegalEntity $legal_entity) {
        $this->card = $card;
        $this->legal_entity = $legal_entity;
    }

    /**
     * @inheritcod
     */
    function get() : ResourceCollection {
        return BonusBalanceResource::collection( BonusBalance::get() );
    }

    /**
     * @inheritcod
     */
    function store( Array $data ) : JsonResource|Bool{
        $bonus_balance = new BonusBalance( $data );
        $bonus_balance->card()->associate( $this->card );
        $bonus_balance->legal_entity()->associate($this->legal_entity);
        $bonus_balance->save();
        return new BonusBalanceResource( $bonus_balance );
    }

    /**
     * @inheritcod
     */
    function update( Int $id, Array $data ) : JsonResource{
        $bonus_balance = BonusBalance::findOrFail( $id );
        foreach( $data as $key => $value ){
            $bonus_balance->{$key} = $value;
        }
        $bonus_balance->save();
        return new BonusBalanceResource( $bonus_balance );
    }

    /**
     * @inheritcod
     */
    function delete( Int $id ) : Bool{
        $bonus_balance = BonusBalance::findOrFail( $id );
        if( empty( $bonus_balance ) ){
            return false;
        }
        return $bonus_balance->delete();
    }

    /**
     * @inheritcod
     */
    function changeBalance( float $amount ): JsonResource
    {
        $bonus_balance = $this->getEntity();

        if( empty( $bonus_balance ) ){
            return $this->store(
                [
                    'sum' => $amount
                ]
            );
        }else{
            return $this->update(
                $bonus_balance->id,
                [
                    'sum' => $bonus_balance->sum + $amount
                ]
            );
        }
    }

    /**
     * @inheritcod
     */
    function getEntity(): ?BonusBalance
    {
        return BonusBalance::findByCardAndLegalEntity( $this->card, $this->legal_entity );
    }
}
