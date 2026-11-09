<?php
namespace Solt\Bonus\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Solt\Bonus\Http\Requests\Card\FindRequest;
use Solt\Bonus\Http\Requests\Card\StoreRequest;
use Solt\Bonus\Http\Requests\Card\UpdateRequest;
use Solt\Bonus\Http\Requests\CardOperation\CancelRequest;
use Solt\Bonus\Http\Requests\CardOperation\DecreaseRequest;
use Solt\Bonus\Http\Requests\CardOperation\DecreaseReturnRequest;
use Solt\Bonus\Http\Requests\CardOperation\IncreaseRequest;
use Solt\Bonus\Http\Requests\CardOperation\IncreaseReturnRequest;
use Solt\Bonus\Http\Requests\CardOperation\MoveRequest;
use Solt\Bonus\Services\CardOperationService;
use Solt\Bonus\Services\CardService;

class CardController extends Controller{

    protected CardService $card_service;
    protected CardOperationService $card_operation_service;

    public function __construct( CardService $card_service, CardOperationService $card_operation_service ) {
        $this->card_service = $card_service;
        $this->card_operation_service = $card_operation_service;
    }

    /**
     * Get bonus card
     * @param String $short_code
     * @return Response
     */
    function get( String $short_code ){
        $card_item = $this->card_service->getItem( $short_code );
        if( $card_item === false ){
            return response(['message' => 'Карта не найдена'], 202);
        }
        return response($card_item->toJson(), 200);
    }


    /**
     * Create bonus card
     * @param StoreRequest $request
     * @return Response
     */
    function store( StoreRequest $request ){
        $new_card_item = $this->card_service->store( $request->all() );
        return response($new_card_item->toJson(), 201);
    }

    /**
     * Update bonus card
     * @param String $short_code
     * @param UpdateRequest $request
     * @return Response
     */
    function update( String $short_code, UpdateRequest $request ){
        $updated_card_item = $this->card_service->update( $short_code, $request->all() );
        if( $updated_card_item === false ){
            return response(['message' => 'Не удалось обновить данные карты'], 202);
        }
        return response($updated_card_item->toJson(), 200);
    }

    /**
     * Delete bonus card
     * @param String $short_code
     * @return Response
     */
    function delete( String $short_code ){
        $delete_card_result = $this->card_service->delete( $short_code );
        if( $delete_card_result ){
            return response(['message' => 'Карта успешно удалена'], 204);
        }
        return response(['message' => 'Не удалось удалить карту'], 202);
    }

    /**
     * Get bonus card balance
     * @param String $short_code
     * @return Response
     */
    function balance( String $short_code ){
        $card_balance_result = $this->card_service->getBalance( $short_code );
        if( $card_balance_result === false ){
            return response(['message' => 'Карта не найдена'], 202);
        }
        return response(['balance' => $card_balance_result], 200);
    }

    /**
     * Increase bonus card balance
     * @param IncreaseRequest $request
     * @return Response
     */
    function increase( IncreaseRequest $request ){
        $new_card_operation = $this->card_operation_service->store( $request->all() );
        if( $new_card_operation === false ){
            return response(['message' => 'Нулевой баланс'], 202);
        }
        return response($new_card_operation->toJson(), 201);
    }

    /**
     * Increase bonus card balance - retrun operation
     * @param IncreaseReturnRequest $request
     * @return Response
     */
    function increaseReturn( IncreaseReturnRequest $request ){
        $new_card_operation = $this->card_operation_service->store( $request->all() );
        if( $new_card_operation === false ){
            return response(['message' => 'Нулевой баланс'], 202);
        }
        return response($new_card_operation->toJson(), 201);
    }

    /**
     * Cancel bonus card operation
     * @param CancelRequest $request
     * @return Response
     */
    function cancel( CancelRequest $request ){
        $cancel_card_operation = $this->card_operation_service->delete( $request->get('short_code'), $request->get('document_number'), $request->get('document_type') );
        if( $cancel_card_operation ){
            return response(['message' => 'Операция успешно отменена'], 204);
        }
        return response(['message' => 'Не удалось отменить операцию'], 202);
    }

    /**
     * Decrease bonus card balance
     * @param DecreaseRequest $request
     * @return Response
     */
    function decrease( DecreaseRequest $request ){
        $new_card_operation = $this->card_operation_service->store( $request->all() );
        if( $new_card_operation === false ){
            return response(['message' => 'Нулевой баланс'], 202);
        }
        return response($new_card_operation->toJson(), 201);
    }

    /**
     * Decrease bonus card balance - retrun operation
     * @param DecreaseReturnRequest $request
     * @return Response
     */
    function decreaseReturn( DecreaseReturnRequest $request ){
        $new_card_operation = $this->card_operation_service->store( $request->all() );
        if( $new_card_operation === false ){
            return response(['message' => 'Нулевой баланс'], 202);
        }
        return response($new_card_operation->toJson(), 201);
    }

    /**
     * Move card balances
     * @param MoveRequest $request
     * @return Response
     */
    function move( MoveRequest $request ){
        $card_balances_move_result = $this->card_operation_service->move(
            $request->get('short_code_from'),
            $request->get('short_code_to'),
            $request->get('document_number'),
            $request->get('document_date'),
            $request->get('document_type')
        );
        if( $card_balances_move_result === false ){
            return response(['message' => 'Ошибка переноса баланса'], 202);
        }
        return response(['message' => 'Баланс успешно перенесен'], 200);
    }

    /**
     * Find card by fields
     * @param FindRequest $request
     * @return Response
     */
    function find( FindRequest $request ){
        $card = $this->card_service->findItem( $request->all() );
        if( $card === false ){
            return response(['message' => 'Карта не найдена'], 202);
        }
        return response($card->toJson(), 200);
    }
}
