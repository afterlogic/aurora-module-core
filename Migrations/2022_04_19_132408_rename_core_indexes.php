<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class RenameCoreIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::connection()->beginTransaction();

        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::schema()->table('core_tenants', function (Blueprint $table)
        {
            $table->renameIndex('ccore_tenants_name_index', 'core_tenants_name_index');
        });
        Capsule::schema()->table('core_groups', function (Blueprint $table)
        {
            $table->renameIndex('ccore_groups_name_index', 'core_groups_name_index');
        });

        Capsule::connection()->commit();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::connection()->beginTransaction();

        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::schema()->table('core_tenants', function (Blueprint $table)
        {
            $table->renameIndex('core_tenants_name_index', 'ccore_tenants_name_index');
        });
        Capsule::schema()->table('core_groups', function (Blueprint $table)
        {
            $table->renameIndex('core_groups_name_index', 'ccore_groups_name_index');
        });

        Capsule::connection()->commit();
    }
}
