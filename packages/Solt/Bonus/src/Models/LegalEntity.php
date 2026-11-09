<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Solt\Bonus\Database\Factories\LegalEntityFactory;

class LegalEntity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'inn',
        'name'
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return LegalEntityFactory::new();
    }

    /**
     * Получение элемента по ИНН
     *
     * @param String $inn
     * @return Card
     */
    static function findByInn( String $inn ){
        return self::whereNull('deleted_at')->where('inn', $inn)->first();
    }

    /**
     * Получение операций по юр. лицу
     */
    public function operations(){
        return $this->hasMany( CardOperation::class );
    }

    /**
     * Получение аптек
     */
    public function pharmacies(){
        return $this->hasMany( Pharmacy::class );
    }

    /**
     * Получение балансов
     */
    public function bonusBalances(){
        return $this->hasMany( BonusBalance::class );
    }
}
