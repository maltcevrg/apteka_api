<?php
namespace Solt\Bonus\Interfaces\LegalEntity;

use Illuminate\Http\Resources\Json\JsonResource;
use Solt\Bonus\Interfaces\Base\DeleteInterface;
use Solt\Bonus\Interfaces\Base\GetInterface;
use Solt\Bonus\Interfaces\Base\StoreInterface;
use Solt\Bonus\Interfaces\Base\UpdateInterface;

interface LegalEntityInterface extends GetInterface, StoreInterface, UpdateInterface, DeleteInterface{

    /**
     * Получение юр. лица
     *
     * @param String $inn
     * @return JsonResource|Bool
     */
    function getItem( String $inn ) : JsonResource|Bool;
}
