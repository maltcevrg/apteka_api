<?php
namespace Solt\Bonus\Interfaces\Base;

use Illuminate\Http\Resources\Json\ResourceCollection;

interface GetInterface{
    /**
     * Получение списка
     *
     * @return ResourceCollection;
     */
    function get() : ResourceCollection;

}
