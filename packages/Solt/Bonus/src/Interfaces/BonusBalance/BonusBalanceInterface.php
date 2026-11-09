<?php
namespace Solt\Bonus\Interfaces\BonusBalance;

use Illuminate\Http\Resources\Json\JsonResource;
use Solt\Bonus\Interfaces\Base\DeleteInterface;
use Solt\Bonus\Interfaces\Base\GetInterface;
use Solt\Bonus\Interfaces\Base\StoreInterface;
use Solt\Bonus\Interfaces\Base\UpdateInterface;
use Solt\Bonus\Models\BonusBalance;


interface BonusBalanceInterface extends GetInterface, StoreInterface, UpdateInterface, DeleteInterface{

    /**
     * Увеличение баланса
     *
     * @param Float $amount сумма
     * @return JsonResource
     */
    function changeBalance( Float $amount ) : JsonResource;

    /**
     * Получение элемента
     *
     * @return ?BonusBalance
     */
    function getEntity() : ?BonusBalance;
}
