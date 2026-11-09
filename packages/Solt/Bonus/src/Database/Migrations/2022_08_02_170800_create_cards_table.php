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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('short_code', 255)->index();
            $table->string('firstname', 255);
            $table->string('name', 255);
            $table->string('lastname', 255)->nullable();
            $table->date('birthdate');
            $table->string('email', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->unsignedTinyInteger('gender');
            $table->boolean('newsletter_agree')->default(false);
            $table->string('guid', 255)->nullable();
            $table->unsignedTinyInteger('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cards');
    }
};
