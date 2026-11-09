<?php
namespace Solt\Bonus\Interfaces\Pharmacy;

use Illuminate\Http\Resources\Json\JsonResource;
use Solt\Bonus\Interfaces\Base\DeleteInterface;
use Solt\Bonus\Interfaces\Base\GetInterface;
use Solt\Bonus\Interfaces\Base\StoreInterface;
use Solt\Bonus\Interfaces\Base\UpdateInterface;

interface PharmacyInterface extends GetInterface, StoreInterface, UpdateInterface, DeleteInterface{

    /**
     * Получение аптеки
     *
     * @param Int $pharmacy_number
     * @return JsonResource|Bool
     */
    function getItem( Int $pharmacy_number ) : JsonResource|Bool;

    /**
     * Обновление юр. лица аптеки
     *
     * @param Int $id
     * @param Int $legal_entity_id
     * @return JsonResource|Bool
     */
    function updateLegalEntity( Int $id, Int $legal_entity_id ) : JsonResource|Bool;
}
