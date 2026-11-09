<?php
namespace Solt\Bonus\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Solt\Bonus\Http\Resources\LegalEntityResource;
use Solt\Bonus\Interfaces\LegalEntity\LegalEntityInterface;
use Solt\Bonus\Models\LegalEntity;

class LegalEntityService implements LegalEntityInterface{

    /**
     * @inheritcod
     */
    function getItem( String $inn ): JsonResource|Bool {
        $legal_entity = LegalEntity::findByInn( $inn );
        if( empty( $legal_entity ) ){
            return false;
        }
        return new LegalEntityResource( $legal_entity );
    }

    /**
     * @inheritcod
     */
    function get() : ResourceCollection {
        return LegalEntityResource::collection( LegalEntity::get() );
    }

    /**
     * @inheritcod
     */
    function store( Array $data ) : JsonResource|Bool{
        $legal_entity = new LegalEntity( $data );
        $legal_entity->save();
        return new LegalEntityResource( $legal_entity );
    }

    /**
     * @inheritcod
     */
    function update( Int $id, Array $data ) : JsonResource{
        $legal_entity = LegalEntity::findOrFail( $id );
        foreach( $data as $key => $value ){
            $legal_entity->{$key} = $value;
        }
        $legal_entity->save();
        return new LegalEntityResource( $legal_entity );
    }

    /**
     * @inheritcod
     */
    function delete( Int $id ) : Bool{
        $legal_entity = LegalEntity::findOrFail( $id );
        if( empty( $legal_entity ) ){
            return false;
        }
        return $legal_entity->delete();
    }

}
