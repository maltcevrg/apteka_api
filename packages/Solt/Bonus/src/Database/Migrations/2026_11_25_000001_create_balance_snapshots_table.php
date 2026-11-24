<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Срезы остатков на конец месяца по паре (карта, юрлицо) и пометки
     * на операциях, в какой закрытый месяц они вошли.
     */
    public function up(): void
    {
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('period');                                   // первое число месяца
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('legal_entity_id')->nullable(); // NULL — переносы без юрлица (старые данные)

            $table->decimal('opening', 12, 2)->default(0);            // сальдо на начало месяца
            $table->decimal('increased', 12, 2)->default(0);          // начислено (action 0)
            $table->decimal('decreased', 12, 2)->default(0);          // списано (action 1), положительное число
            $table->decimal('returned_increase', 12, 2)->default(0);  // возврат начисления (action 3), положительное
            $table->decimal('returned_decrease', 12, 2)->default(0);  // возврат списания (action 4)
            $table->decimal('moved', 12, 2)->default(0);              // переносы (action 2), со знаком
            $table->decimal('cancelled_increase', 12, 2)->default(0); // отменено начислений в этом месяце, положительное
            $table->decimal('cancelled_decrease', 12, 2)->default(0); // отменено списаний в этом месяце, положительное
            $table->decimal('cancelled_other', 12, 2)->default(0);    // отменено возвратов/переносов, со знаком суммы операции
            $table->decimal('closing', 12, 2)->default(0);            // сальдо на конец месяца

            $table->timestamps();

            $table->unique(['period', 'card_id', 'legal_entity_id']);
            $table->index(['period', 'legal_entity_id']);
            $table->foreign('card_id')->references('id')->on('cards');
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities');
        });

        Schema::table('card_operations', function (Blueprint $table) {
            // В какой закрытый месяц вошла сама операция и её отмена
            $table->date('closed_period')->nullable()->after('amount');
            $table->date('cancel_closed_period')->nullable()->after('closed_period');
            $table->index(['closed_period', 'document_date']);
            $table->index(['cancel_closed_period', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->dropIndex(['closed_period', 'document_date']);
            $table->dropIndex(['cancel_closed_period', 'deleted_at']);
            $table->dropColumn(['closed_period', 'cancel_closed_period']);
        });
        Schema::dropIfExists('balance_snapshots');
    }
};
