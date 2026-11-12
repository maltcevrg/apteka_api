<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Индексы для полей, по которым ищут при каждой операции:
     * card_operations.document_number — отмена операции,
     * cards.phone — поиск карты по телефону,
     * bonus_balances (card_id, legal_entity_id) — изменение баланса.
     */
    public function up(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->index('document_number');
        });
        Schema::table('cards', function (Blueprint $table) {
            $table->index('phone');
        });
        Schema::table('bonus_balances', function (Blueprint $table) {
            $table->index(['card_id', 'legal_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->dropIndex(['document_number']);
        });
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['phone']);
        });
        Schema::table('bonus_balances', function (Blueprint $table) {
            $table->dropIndex(['card_id', 'legal_entity_id']);
        });
    }
};
