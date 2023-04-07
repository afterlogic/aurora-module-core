<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateCoreFulltextIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        $sm = Capsule::connection()->getDoctrineSchemaManager();

        Capsule::schema()->table('core_groups', function (Blueprint $table) use ($prefix, $sm) {
            $doctrineTable = $sm->listTableDetails($prefix . 'core_groups');
            if (!$doctrineTable->hasIndex('core_groups_tenantid_index')) {
                $table->index(['TenantId']);
            }
            if (!$doctrineTable->hasIndex('ccore_groups_name_index')) {
                Capsule::connection()->statement("CREATE FULLTEXT INDEX ccore_groups_name_index ON {$prefix}core_groups (Name)");
            }
        });
        Capsule::schema()->table('core_auth_tokens', function (Blueprint $table) use ($prefix, $sm) {
            $doctrineTable = $sm->listTableDetails($prefix . 'core_auth_tokens');
            if (!$doctrineTable->hasIndex('core_auth_tokens_userid_index')) {
                $table->index(['UserId']);
            }
        });
        Capsule::schema()->table('core_min_hashes', function (Blueprint $table) use ($prefix, $sm) {
            $doctrineTable = $sm->listTableDetails($prefix . 'core_min_hashes');
            if (!$doctrineTable->hasIndex('core_min_hashes_userid_index')) {
                $table->index(['UserId']);
            }
        });
        $doctrineTable = $sm->listTableDetails($prefix . 'core_tenants');
        if (!$doctrineTable->hasIndex('ccore_tenants_name_index')) {
            Capsule::connection()->statement("CREATE FULLTEXT INDEX ccore_tenants_name_index ON {$prefix}core_tenants (Name)");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('core_tenants', function (Blueprint $table) {
            $table->dropIndex('ccore_tenants_name_index');
        });
        Capsule::schema()->table('core_groups', function (Blueprint $table) {
            $table->dropIndex(['TenantId']);
            $table->dropIndex('ccore_groups_name_index');
        });
        Capsule::schema()->table('core_auth_tokens', function (Blueprint $table) {
            $table->dropIndex(['UserId']);
        });
        Capsule::schema()->table('core_min_hashes', function (Blueprint $table) {
            $table->dropIndex(['UserId']);
        });
    }
}
