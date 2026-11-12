<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Solt\Bonus\Database\Factories\CardFactory;

class Card extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'short_code',
        'firstname',
        'name',
        'lastname',
        'birthdate',
        'email',
        'phone',
        'city',
        'gender',
        'newsletter_agree',
        'guid',
        'status'
    ];

    static $genders = [
        0 => 'м',
        1 => 'ж'
    ];

    static $statuses = [
        0 => 'действительна',
        1 => 'аннулирована',
        2 => 'заменена'
    ];

    static $avaliable_status = 0;
    static $not_avaliable_status = 1;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return CardFactory::new();
    }

    /**
     * Получение элемента по штрих-коду
     *
     * @param String $short_code
     * @param Bool $lock  Заблокировать строку карты до конца транзакции (SELECT ... FOR UPDATE)
     * @return Card
     */
    static function findByShortCode( String $short_code, Bool $lock = false ){
        $query = self::where('short_code', $short_code)->whereNull('deleted_at')->where('status', self::$avaliable_status);
        if( $lock ){
            $query->lockForUpdate();
        }
        return $query->first();
    }

    /**
     * Получение элемента по параметрам
     *
     * @param Array $data
     * @return Card
     */
    static function findByParams( Array $data ){
        return self::where( function($query) use ($data) {
            foreach( $data as $key => $value ){
                $query->where($key, $value);
            }
        })->whereNull('deleted_at')->where('status', self::$avaliable_status)->first();
    }

    /**
     * Получение баланса по карте
     *
     * @return Float
     */
    function getBalance(){
        // Свежий запрос, а не кэшированное отношение: внутри транзакции
        // баланс должен отражать текущее состояние базы.
        return round((float) $this->balances()->sum('sum'), 2);
    }

    /**
     * Получение операций по карте
     */
    public function operations(){
        return $this->hasMany( CardOperation::class );
    }

    /**
     * Получение баланса по карте
     */
    public function balances(){
        return $this->hasMany( BonusBalance::class );
    }
}
