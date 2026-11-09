<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Solt\Bonus\Database\Factories\CardOperationFactory;

class CardOperation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'action',
        'document_date',
        'document_number',
        'document_type',
        'amount'
    ];

    static $increase_action = 0;
    static $decreser_action = 1;

    static $increase_return_action = 3;
    static $decreser_return_action = 4;

    static $move_action = 2;


    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return CardOperationFactory::new();
    }

    /**
     * Получение элемента по реквизитам
     *
     * @param String $short_code
     * @param String $document_number
     * @param String $document_type
     * @return CardOperation
     */
    static function findByRequisites( $short_code, $document_number, $document_type ){
        return self::whereHas('card', function ( $query ) use ($short_code) {
            $query->where('short_code', $short_code);
        })->whereNull('deleted_at')->where('document_number', $document_number)->where('document_type', $document_type)->first();
    }

    /**
     * Получение карты
     */
    public function card(){
        return $this->belongsTo(Card::class);
    }

    /**
     * Получение юр. лица
     */
    public function legal_entity(){
        return $this->belongsTo(LegalEntity::class);
    }

    /**
     * Получение аптеки
     */
    public function pharmacy(){
        return $this->belongsTo(Pharmacy::class);
    }
}
