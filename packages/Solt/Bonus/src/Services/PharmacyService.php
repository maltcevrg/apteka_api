<?php
namespace Solt\Bonus\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Solt\Bonus\Http\Resources\PharmacyResource;
use Solt\Bonus\Interfaces\Pharmacy\PharmacyInterface;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;

class PharmacyService implements PharmacyInterface{

    /**
     * @inheritcod
     */
    function getItem(Int $pharmacy_number): JsonResource|Bool {
        $pharmacy = Pharmacy::findByNumber( $pharmacy_number );
        if( empty( $pharmacy ) ){
            return false;
        }
        return new PharmacyResource( $pharmacy );
    }

    /**
     * @inheritcod
     */
    function get() : ResourceCollection {
        return PharmacyResource::collection( Pharmacy::with('legal_entity')->get() );
    }

    /**
     * @inheritcod
     */
    function store( Array $data ) : JsonResource|Bool{
        $legal_entity = LegalEntity::find( $data['legal_entity'] );
        if( empty( $legal_entity ) ){
            return false;
        }
        $pharmacy = new Pharmacy( $data );
        $pharmacy->legal_entity()->associate($legal_entity);
        $pharmacy->save();
        return new PharmacyResource( $pharmacy );
    }

    /**
     * @inheritcod
     */
    function update( Int $id, Array $data ) : JsonResource{
        $pharmacy = Pharmacy::with('legal_entity')->findOrFail( $id );
        foreach( $data as $key => $value ){
            $pharmacy->{$key} = $value;
        }
        $pharmacy->save();
        return new PharmacyResource( $pharmacy );
    }

    /**
     * @inheritcod
     */
    function delete( Int $id ) : Bool{
        $pharmacy = Pharmacy::findOrFail( $id );
        if( empty( $pharmacy ) ){
            return false;
        }
        return $pharmacy->delete();
    }

    /**
     * @inheritcod
     */
    function updateLegalEntity( Int $id, Int $legal_entity_id ) : JsonResource|Bool{
        $pharmacy = Pharmacy::with('legal_entity')->findOrFail( $id );
        if( $pharmacy->legal_entity->id === $legal_entity_id ){
            return false;
        }

        $data = [
            'name'          => $pharmacy->name,
            'number'        => $pharmacy->number,
            'legal_entity'  => $legal_entity_id
        ];

        return $this->store($data);
    }

}
