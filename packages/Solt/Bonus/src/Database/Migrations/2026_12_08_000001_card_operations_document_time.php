<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Время документа (необязательное) — для точной хронологии внутри дня. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->time('document_time')->nullable()->after('document_date');
            $table->index(['card_id', 'document_date'], 'card_operations_card_date_idx');
            $table->index(['pharmacy_id', 'document_number', 'document_type'], 'card_operations_pharmacy_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->dropIndex('card_operations_pharmacy_doc_idx');
            $table->dropIndex('card_operations_card_date_idx');
            $table->dropColumn('document_time');
        });
    }
};
