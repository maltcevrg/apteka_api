<?php

namespace Solt\Bonus\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Solt\Bonus\Database\Factories\CardOperationFactory;

/**
 * Строка журнала операций. Журнал — единственный источник баланса:
 * wallet_after / card_after — остаток кошелька и карты после этой строки.
 *
 * deleted_at здесь НЕ мягкое удаление, а пометка «погашена сторно»:
 * строка остаётся частью цепочки остатков и всегда читается.
 * Сама отмена — отдельная строка с action = 5 и parent_id на исходную.
 */
class CardOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'document_date',
        'document_number',
        'document_type',
        'amount',
        'offline',
        'amount_requested',
        'user_id',
        'comment',
    ];

    protected $casts = [
        'deleted_at'   => 'datetime',
        'amount'       => 'float',
        'wallet_after' => 'float',
        'card_after'   => 'float',
        'offline'      => 'boolean',
        'amount_requested' => 'float',
    ];

    static $increase_action = 0;
    static $decreser_action = 1;

    static $increase_return_action = 3;
    static $decreser_return_action = 4;

    static $move_action = 2;

    static $cancel_action = 5; // сторно: отмена операции parent_id

    /**
     * Нарастающий итог считается при вставке, если не задан явно
     * (LedgerService задаёт сам; фабрики в тестах — нет).
     */
    protected static function booted()
    {
        static::creating(function (CardOperation $operation) {
            // Суммы в журнале хранятся с точностью до копеек (2 знака после запятой).
            $operation->amount = round((float) $operation->amount, 2);
            if ($operation->card_after === null || $operation->wallet_after === null) {
                $amount = (float) $operation->amount;
                $operation->wallet_after = round(\Solt\Bonus\Services\LedgerService::walletBalance($operation->card_id, $operation->legal_entity_id) + $amount, 2);
                $operation->card_after   = round(\Solt\Bonus\Services\LedgerService::cardBalance($operation->card_id) + $amount, 2);
            } else {
                $operation->wallet_after = round((float) $operation->wallet_after, 2);
                $operation->card_after   = round((float) $operation->card_after, 2);
            }
        });

        // Строка задним числом (не через LedgerService::append, например из фабрики/импорта):
        // остатки более поздних строк карты пересчитываются, чтобы цепочка оставалась хронологической.
        static::created(function (CardOperation $operation) {
            $docDate = substr((string) $operation->document_date, 0, 10);
            $later = static::where('card_id', $operation->card_id)
                ->whereDate('document_date', '>', $docDate)->exists();
            if ($later) {
                \Solt\Bonus\Services\LedgerService::rebuildCard((int) $operation->card_id);
                $operation->refresh();
            }
        });
    }


    /** Кто провёл операцию вручную из админ-панели (null — касса). */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

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
     * Исходная операция (для сторно)
     */
    public function parent(){
        return $this->belongsTo(CardOperation::class, 'parent_id');
    }

    /**
     * Сторно этой операции, если она отменена
     */
    public function cancellation(){
        return $this->hasOne(CardOperation::class, 'parent_id');
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
