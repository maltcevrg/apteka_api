<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Офлайн-операции касс: признак offline и запрошенная сумма,
 * если списание проведено частично (баланса не хватило, баланс стал 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->boolean('offline')->default(false)->after('amount');
            $table->decimal('amount_requested', 12, 2)->nullable()->after('offline');
            $table->index('offline');
        });
    }

    public function down(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->dropIndex(['offline']);
            $table->dropColumn(['offline', 'amount_requested']);
        });
    }
};
