<?php
namespace Solt\Bonus\Interfaces\Base;

interface DeleteInterface{

    /**
     * Удаление элемента
     *
     * @param Int $id идентификатор
     * @return Bool
     */
    function delete( Int $id ) : Bool;

}
