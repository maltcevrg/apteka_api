<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал операций становится единственным источником баланса:
 * каждая строка хранит остаток кошелька и карты после себя,
 * отмена — отдельная строка (сторно) со ссылкой на исходную.
 * Таблица bonus_balances больше не нужна.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
            $table->decimal('wallet_after', 12, 2)->default(0)->after('amount');   // остаток кошелька (карта + юрлицо) после операции
            $table->decimal('card_after', 12, 2)->default(0)->after('wallet_after'); // остаток карты после операции
            $table->unsignedBigInteger('parent_id')->nullable()->after('card_after'); // у сторно — отменённая операция

            $table->unique('parent_id');
            $table->foreign('parent_id')->references('id')->on('card_operations');
            $table->index(['card_id', 'id']);
            $table->index(['card_id', 'legal_entity_id', 'id']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedBigInteger('legal_entity_id')->nullable()->after('status'); // юрлицо-владелец карты: по первой операции
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities');
        });

        Schema::dropIfExists('bonus_balances');
    }

    public function down(): void
    {
        Schema::create('bonus_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('card_id');
            $table->decimal('sum')->default(0.0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities');
            $table->foreign('card_id')->references('id')->on('cards');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropColumn('legal_entity_id');
        });

        Schema::table('card_operations', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropUnique(['parent_id']);
            $table->dropIndex(['card_id', 'id']);
            $table->dropIndex(['card_id', 'legal_entity_id', 'id']);
            $table->dropColumn(['wallet_after', 'card_after', 'parent_id']);
        });
    }
};
