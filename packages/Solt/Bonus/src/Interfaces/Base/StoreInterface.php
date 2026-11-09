<?php
namespace Solt\Bonus\Interfaces\Base;

use Illuminate\Http\Resources\Json\JsonResource;

interface StoreInterface{

    /**
     * Создание элемента
     *
     * @param Array $data данные полей элемента
     * @return JsonResource|Bool
     */
    function store( Array $data ) : JsonResource|Bool;
}
