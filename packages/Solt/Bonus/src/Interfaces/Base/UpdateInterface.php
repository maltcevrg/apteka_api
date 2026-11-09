<?php
namespace Solt\Bonus\Interfaces\Base;

use Illuminate\Http\Resources\Json\JsonResource;

interface UpdateInterface{

    /**
     * Обновление элемента
     *
     * @param Int $id идентификатор
     * @param Array $data данные полей элемента
     * @return JsonResource
     */
    function update( Int $id, Array $data ) : JsonResource;
}
