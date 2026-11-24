<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Убирает таблицу срезов и служебные поля, если они были созданы
     * ранее удалённой миграцией 2026_11_25_000001. На чистой базе ничего не делает.
     */
    public function up(): void
    {
        Schema::dropIfExists('balance_snapshots');

        if (Schema::hasColumn('card_operations', 'closed_period')) {
            Schema::table('card_operations', function (Blueprint $table) {
                $table->dropIndex(['closed_period', 'document_date']);
                $table->dropIndex(['cancel_closed_period', 'deleted_at']);
                $table->dropColumn(['closed_period', 'cancel_closed_period']);
            });
        }
    }

    public function down(): void
    {
        // Возврат не предусмотрен
    }
};
