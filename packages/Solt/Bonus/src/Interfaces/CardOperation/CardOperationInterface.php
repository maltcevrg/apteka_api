<?php
namespace Solt\Bonus\Interfaces\CardOperation;

use Illuminate\Http\Resources\Json\JsonResource;
use Solt\Bonus\Interfaces\Base\GetInterface;
use Solt\Bonus\Interfaces\Base\StoreInterface;

interface CardOperationInterface extends GetInterface, StoreInterface{

    /**
     * Получение операции
     *
     * @param Int $id идентификатор
     * @return String
     */
    function getItem( Int $id ) : JsonResource;

    /**
     * Удаление операции
     *
     * @param String $short_code штрих-код карты
     * @param String $document_number номер документа
     * @param String $document_type тип документа
     * @return Bool
     */
    function delete( String $short_code, String $document_number, String $document_type ) : Bool;

    /**
     * Перенос баланса с карты на карту
     *
     * @param String $short_code_from штрих-код карты источника
     * @param String $short_code_to штрих-код карты получателя
     * @param String $document_number номер документа
     * @param String $document_date дата документа
     * @param String $document_type тип документа
     * @return Bool
     */
    function move( String $short_code_from, String $short_code_to,  String $document_number, String $document_date, String $document_type ) : Bool;
}
