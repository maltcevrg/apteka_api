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
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedInteger('number')->index();
            $table->text('name');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
        });

        Schema::dropIfExists('pharmacies');
    }
};
