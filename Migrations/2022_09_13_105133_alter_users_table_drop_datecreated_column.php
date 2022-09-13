<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterUsersTableDropDatecreatedColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->table('core_users', function (Blueprint $table) {
            $table->dropColumn('DateCreated');
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
            $table->datetime('DateCreated')->nullable()->after('Role');
        });
    }
}
