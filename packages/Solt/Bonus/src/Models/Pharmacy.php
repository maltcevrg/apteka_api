<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Solt\Bonus\Database\Factories\PharmacyFactory;

class Pharmacy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'name'
    ];

      /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return PharmacyFactory::new();
    }

    /**
     * Получение элемента по номеру
     *
     * @param Int $number
     * @return Card
     */
    static function findByNumber( Int $number ){
        return self::with('legal_entity')->whereNull('deleted_at')->where('number', $number)->first();
    }

    /**
     * Получение операций по аптеке
     */
    public function operations(){
        return $this->hasMany( CardOperation::class );
    }

    /**
     * Получение юр. лица
     */
    public function legal_entity(){
        return $this->belongsTo(LegalEntity::class);
    }
}
