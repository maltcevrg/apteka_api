<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bonus_balances', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropForeign(['card_id']);
        });

        Schema::dropIfExists('bonus_balances');
    }
};
