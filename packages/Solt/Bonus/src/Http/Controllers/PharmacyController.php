<?php
namespace Solt\Bonus\Http\Controllers;

use App\Http\Controllers\Controller;
use Solt\Bonus\Http\Requests\Pharmacy\StoreRequest;
use Solt\Bonus\Http\Requests\Pharmacy\UpdateLegalEntityRequest;
use Solt\Bonus\Http\Requests\Pharmacy\UpdateRequest;
use Solt\Bonus\Services\PharmacyService;

class PharmacyController extends Controller{

    protected PharmacyService $pharmacy_service;

    public function __construct( PharmacyService $pharmacy_service ) {
        $this->pharmacy_service = $pharmacy_service;
    }

    /**
     * Get pharmacy list
     * @return Response
     */
    function list(){
        $pharmacy_item = $this->pharmacy_service->get();
        return response($pharmacy_item->toJson(), 200);
    }

    /**
     * Get pharmacy
     * @param String $number
     * @return Response
     */
    function get( String $number ){
        $pharmacy_item = $this->pharmacy_service->getItem( $number );
        if( $pharmacy_item === false ){
            return response(['message' => 'Аптека не найдена'], 202);
        }
        return response($pharmacy_item->toJson(), 200);
    }

    /**
     * Create pharmacy
     * @param StoreRequest $request
     * @return Response
     */
    function store( StoreRequest $request ){
        $new_pharmacy_item = $this->pharmacy_service->store( $request->all() );
        return response($new_pharmacy_item->toJson(), 201);
    }

    /**
     * Update pharmacy
     * @param String $number
     * @param UpdateRequest $request
     * @return Response
     */
    function update( String $number, UpdateRequest $request ){
        $pharmacy_item = $this->pharmacy_service->getItem( $number );
        if( $pharmacy_item === false ){
            return response(['message' => 'Аптека не найдена'], 202);
        }
        $updated_pharmacy_item = $this->pharmacy_service->update( $pharmacy_item->id, $request->all() );
        if( $updated_pharmacy_item === false ){
            return response(['message' => 'Не удалось обновить данные аптеки'], 202);
        }
        return response($updated_pharmacy_item->toJson(), 200);
    }

     /**
     * Update pharmacy legal entity
     * @param String $number
     * @param UpdateLegalEntityRequest $request
     * @return Response
     */
    function updateLegalEntity( String $number, UpdateLegalEntityRequest $request ){
        $pharmacy_item = $this->pharmacy_service->getItem( $number );
        if( $pharmacy_item === false ){
            return response(['message' => 'Аптека не найдена'], 202);
        }
        $updated_pharmacy_item = $this->pharmacy_service->updateLegalEntity( $pharmacy_item->id, $request->get('legal_entity') );
        if( $updated_pharmacy_item === false ){
            return response(['message' => 'Не удалось обновить данные об юр. лице у аптеки'], 202);
        }
        return response($updated_pharmacy_item->toJson(), 200);
    }

    /**
     * Delete pharmacy
     * @param String $number
     * @return Response
     */
    function delete( String $number ){
        $pharmacy_item = $this->pharmacy_service->getItem( $number );
        if( $pharmacy_item === false ){
            return response(['message' => 'Аптека не найдена'], 202);
        }
        $delete_pharmacy_result = $this->pharmacy_service->delete( $pharmacy_item->id );
        if( $delete_pharmacy_result ){
            return response(['message' => 'Юо. лицо успешно удалено'], 204);
        }
        return response(['message' => 'Не удалось удалить юр. лицо'], 202);
    }
}
