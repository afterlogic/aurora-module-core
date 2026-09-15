<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AddIdtenantIndexesToCoreUsersTable extends Migration
{
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        $table = $prefix . 'core_users';

        $sm = Capsule::connection()->getDoctrineSchemaManager();
        $doctrineTable = $sm->listTableDetails($table);

        if (!$doctrineTable->hasIndex('core_users_idtenant_index')) {
            Capsule::schema()->table('core_users', function (Blueprint $table) {
                $table->index('IdTenant', 'core_users_idtenant_index');
            });
        }

        if (!$doctrineTable->hasIndex('core_users_idtenant_role_index')) {
            Capsule::schema()->table('core_users', function (Blueprint $table) {
                $table->index(['IdTenant', 'Role'], 'core_users_idtenant_role_index');
            });
        }
    }

    public function down()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        $table = $prefix . 'core_users';

        $sm = Capsule::connection()->getDoctrineSchemaManager();
        $doctrineTable = $sm->listTableDetails($table);

        if ($doctrineTable->hasIndex('core_users_idtenant_role_index')) {
            Capsule::schema()->table('core_users', function (Blueprint $table) {
                $table->dropIndex('core_users_idtenant_role_index');
            });
        }

        if ($doctrineTable->hasIndex('core_users_idtenant_index')) {
            Capsule::schema()->table('core_users', function (Blueprint $table) {
                $table->dropIndex('core_users_idtenant_index');
            });
        }
    }
}
