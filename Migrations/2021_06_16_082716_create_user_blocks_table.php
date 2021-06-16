<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateUserBlocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('user_blocks', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Email')->default('');
            $table->string('IpAddress')->default('');
            $table->integer('ErrorLoginsCount')->default(0);
            $table->integer('Time')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('user_blocks');
    }
}
