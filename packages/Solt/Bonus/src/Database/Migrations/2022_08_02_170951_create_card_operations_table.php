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
        Schema::create('card_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('card_id');
            $table->unsignedSmallInteger('action')->nullable();
            $table->date('document_date');
            $table->string('document_number', 255);
            $table->string('document_type', 255);
            $table->decimal('amount');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities');
            $table->foreign('pharmacy_id')->references('id')->on('pharmacies');
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
        Schema::table('card_operations', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropForeign(['pharmacy_id']);
            $table->dropForeign(['card_id']);

        });

        Schema::dropIfExists('card_operations');
    }
};
