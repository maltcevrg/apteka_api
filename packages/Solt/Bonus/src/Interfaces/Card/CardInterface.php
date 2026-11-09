<?php
namespace Solt\Bonus\Interfaces\Card;

use Illuminate\Http\Resources\Json\JsonResource;
use Solt\Bonus\Interfaces\Base\GetInterface;
use Solt\Bonus\Interfaces\Base\StoreInterface;

interface CardInterface extends GetInterface, StoreInterface{

    /**
     * Получение карты
     *
     * @param String $short_code штрих-код карты
     * @return JsonResource|Bool
     */
    function getItem( String $short_code ) : JsonResource|Bool;

    /**
     * Обновление карты
     *
     * @param String $short_code штрих-код карты
     * @param Array $data данные полей элемента
     * @return JsonResource|Bool
     */
    function update( String $short_code, Array $data ) : JsonResource|Bool;

    /**
     * Удаление карты
     *
     * @param String $short_code штрих-код карты
     * @return Bool
     */
    function delete( String $short_code ) : Bool;

    /**
     * Получение баланса по карте
     *
     * @param String $short_code штрих-код карты
     * @return Float|Bool
     */
    function getBalance( String $short_code ) : Float|Bool;

    /**
     * Поиск карты
     *
     * @param Array $data массив параметров поиска
     * @return JsonResource|Bool
     */
    function findItem( Array $data ) : JsonResource|Bool;
}
