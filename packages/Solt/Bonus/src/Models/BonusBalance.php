<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Solt\Bonus\Database\Factories\BonusBalanceFactory;

class BonusBalance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sum'
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return BonusBalanceFactory::new();
    }

    /**
     * Поиск элемента по карте и юр. лицу
     * @param Card $card
     * @param LegalEntiry $legal_entity
     *
     * @return BonusBalance
     */
    static function findByCardAndLegalEntity( Card $card, LegalEntity $legal_entity ){
        return self::whereNull('deleted_at')->where('card_id', $card->id)->where('legal_entity_id', $legal_entity->id)->first();
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
}
