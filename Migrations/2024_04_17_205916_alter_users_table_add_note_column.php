<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterUsersTableAddNoteColumn extends Migration
{
    /**
     * Performing the migration.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->table('core_users', function (Blueprint $table) {
            $table->text('Note')->nullable()->after('LoginsCount');
        });
    }

    /**
     * Rolling back the migration.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('core_users', function (Blueprint $table) {
            $table->dropColumn('Note');
        });
    }
}
