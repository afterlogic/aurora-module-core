<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterUsersTableAddDefaultTimeZoneColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->table('users', function (Blueprint $table) {
             $table->string('DefaultTimeZone')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('users', function (Blueprint $table) {
            $table->dropColumn('DefaultTimeZone');
       });
    }
}
