<?php
namespace Solt\Bonus\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Expr\Cast\Bool_;
use Solt\Bonus\Http\Resources\CardResource;
use Solt\Bonus\Interfaces\Card\CardInterface;
use Solt\Bonus\Models\Card;

class CardService implements CardInterface{

    /**
     * @inheritcod
     */
    function get() : ResourceCollection {
        return CardResource::collection( Card::get() );
    }

    /**
     * @inheritcod
     */
    function getItem( String $short_code ) : JsonResource|Bool {
        $card = Card::findByShortCode( $short_code );
        if( empty( $card ) ){
            return false;
        }
        return new CardResource( $card );
    }

    /**
     * @inheritcod
     */
    function store( Array $data ) : JsonResource|Bool {
        $card = new Card( $data );
        $card->save();
        return new CardResource( $card );
    }

    /**
     * @inheritcod
     */
    function update( String $short_code, Array $data ) : JsonResource|Bool {
        $card = Card::findByShortCode( $short_code );
        if( empty( $card ) ){
            return false;
        }
        foreach( $data as $key => $value ){
            $card->{$key} = $value;
        }
        $card->save();
        return new CardResource( $card );
    }

    /**
     * @inheritcod
     */
    function delete( String $short_code ) : Bool {
        $card = Card::findByShortCode( $short_code );
        if( empty( $card ) ){
            return false;
        }
        $card->status = Card::$not_avaliable_status;
        $card->save();
        return $card->delete();
    }

    /**
     * @inheritcod
     */
    function getBalance(string $short_code): Float|Bool
    {
        $card = Card::findByShortCode( $short_code );
        if( empty( $card ) ){
            return false;
        }
        return $card->getBalance();
    }

    /**
     * @inheritcod
     */
    function findItem(array $data): JsonResource|Bool
    {
        $card = Card::findByParams( $data );
        if( empty( $card ) ){
            return false;
        }
        return new CardResource( $card );
    }
}
