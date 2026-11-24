<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Срез остатка на конец месяца по паре (карта, юрлицо).
 *
 * closing = opening + increased − decreased − returned_increase + returned_decrease + moved
 *           − cancelled_increase + cancelled_decrease − cancelled_other
 */
class BalanceSnapshot extends Model
{
    protected $fillable = [
        'period', 'card_id', 'legal_entity_id',
        'opening', 'increased', 'decreased', 'returned_increase', 'returned_decrease', 'moved',
        'cancelled_increase', 'cancelled_decrease', 'cancelled_other', 'closing',
    ];

    protected $casts = [
        'period'             => 'date:Y-m-d',
        'opening'            => 'float',
        'increased'          => 'float',
        'decreased'          => 'float',
        'returned_increase'  => 'float',
        'returned_decrease'  => 'float',
        'moved'              => 'float',
        'cancelled_increase' => 'float',
        'cancelled_decrease' => 'float',
        'cancelled_other'    => 'float',
        'closing'            => 'float',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function legal_entity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /**
     * Последний закрытый месяц (первое число) или null
     */
    public static function lastClosedPeriod(): ?string
    {
        $max = self::max('period');
        return $max ? substr($max, 0, 10) : null;
    }
}
