<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateCoreGroupUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('core_group_user', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('GroupId')->unsigned()->index();
            $table->foreign('GroupId')->references('Id')->on('core_groups')->onDelete('cascade');
            $table->integer('UserId')->unsigned()->index();
            $table->foreign('UserId')->references('Id')->on('core_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('core_group_user');
    }
}
