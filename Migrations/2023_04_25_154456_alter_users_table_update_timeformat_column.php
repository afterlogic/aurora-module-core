<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterUsersTableUpdateTimeformatColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->table('core_users', function (Blueprint $table) {
            $table->integer('TimeFormat')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('core_users', function (Blueprint $table) {
            $table->integer('TimeFormat')->nullable()->change();
        });
    }
}
