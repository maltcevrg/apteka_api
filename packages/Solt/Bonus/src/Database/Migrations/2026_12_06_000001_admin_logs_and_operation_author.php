<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал действий в админ-панели и автор/комментарий у ручных операций журнала.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('login', 64)->nullable();     // на случай удаления пользователя
            $table->string('action', 64);                // pharmacy.update, card.adjust, user.create …
            $table->string('subject', 255)->nullable();  // что менялось: «Аптека 5», «Карта DISN32569»
            $table->text('details')->nullable();         // подробности (JSON)
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::table('card_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('parent_id'); // кто провёл вручную из админки
            $table->string('comment', 255)->nullable()->after('user_id');            // причина корректировки
        });
    }

    public function down(): void
    {
        Schema::table('card_operations', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'comment']);
        });
        Schema::dropIfExists('admin_logs');
    }
};
