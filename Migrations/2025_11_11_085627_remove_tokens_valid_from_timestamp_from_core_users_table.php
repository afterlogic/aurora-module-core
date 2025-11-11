<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class RemoveTokensValidFromTimestampFromCoreUsersTable extends Migration
{
    public function up()
    {
        $tableName = 'core_users';
        $column = 'TokensValidFromTimestamp';

        if (Capsule::schema()->hasColumn($tableName, $column)) {
            Capsule::schema()->table($tableName, function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }

    public function down()
    {
        $tableName = 'core_users';
        $column = 'TokensValidFromTimestamp';

        if (!Capsule::schema()->hasColumn($tableName, $column)) {
            Capsule::schema()->table($tableName, function (Blueprint $table) use ($column) {
                $table->integer($column)->default(0);
            });
        }
    }
}
