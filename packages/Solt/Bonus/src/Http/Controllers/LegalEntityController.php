<?php
namespace Solt\Bonus\Http\Controllers;

use App\Http\Controllers\Controller;
use Solt\Bonus\Http\Requests\LegalEntity\StoreRequest;
use Solt\Bonus\Http\Requests\LegalEntity\UpdateRequest;
use Solt\Bonus\Services\LegalEntityService;
use Solt\Bonus\Services\PharmacyService;

class LegalEntityController extends Controller{

    protected LegalEntityService $legal_entity_service;

    public function __construct( LegalEntityService $legal_entity_service ) {
        $this->legal_entity_service = $legal_entity_service;
    }

    /**
     * Get legal entity list
     * @return Response
     */
    function list(){
        $legal_entity_list = $this->legal_entity_service->get();
        return response($legal_entity_list->toJson(), 200);
    }

    /**
     * Get legal entity
     * @param String $inn
     * @return Response
     */
    function get( String $inn ){
        $legal_entity_item = $this->legal_entity_service->getItem( $inn );
        if( $legal_entity_item === false ){
            return response(['message' => 'Юр. лицо не найдено'], 202);
        }
        return response($legal_entity_item->toJson(), 200);
    }

    /**
     * Create legal entity
     * @param StoreRequest $request
     * @return Response
     */
    function store( StoreRequest $request ){
        $new_legal_entity_item = $this->legal_entity_service->store( $request->all() );
        return response($new_legal_entity_item->toJson(), 201);
    }

    /**
     * Update legal entity
     * @param String $inn
     * @param UpdateRequest $request
     * @return Response
     */
    function update( String $inn, UpdateRequest $request ){
        $legal_entity_item = $this->legal_entity_service->getItem( $inn );
        if( $legal_entity_item === false ){
            return response(['message' => 'Юр. лицо не найдено'], 202);
        }
        $updated_legal_entity_item = $this->legal_entity_service->update( $legal_entity_item->id, $request->all() );
        if( $updated_legal_entity_item === false ){
            return response(['message' => 'Не удалось обновить данные юр. лица'], 202);
        }
        return response($updated_legal_entity_item->toJson(), 200);
    }

    /**
     * Delete legal entity
     * @param String $inn
     * @return Response
     */
    function delete( String $inn ){
        $legal_entity_item = $this->legal_entity_service->getItem( $inn );
        if( $legal_entity_item === false ){
            return response(['message' => 'Юр. лицо не найдено'], 202);
        }
        $delete_legal_entity_result = $this->legal_entity_service->delete( $legal_entity_item->id );
        if( $delete_legal_entity_result ){
            return response(['message' => 'Юо. лицо успешно удалено'], 204);
        }
        return response(['message' => 'Не удалось удалить юр. лицо'], 202);
    }
}
