<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterUsersTableAddNoteColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::connection()->statement("ALTER TABLE {$prefix}core_users ADD COLUMN `Note` TEXT NOT NULL AFTER `LoginsCount`");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::connection()->statement("ALTER TABLE {$prefix}core_users DROP COLUMN `Note`");
    }
}
