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
        Capsule::schema()->table('core_groups', function (Blueprint $table)
        {
            $table->index(['TenantId']);
        });
        Capsule::schema()->table('core_auth_tokens', function (Blueprint $table)
        {
            $table->index(['UserId']);
        });
        Capsule::schema()->table('core_min_hashes', function (Blueprint $table)
        {
            $table->index(['UserId']);
        });
        Capsule::statement("CREATE FULLTEXT INDEX ccore_tenants_name_index ON {$prefix}core_tenants (Name)");
        Capsule::statement("CREATE FULLTEXT INDEX ccore_groups_name_index ON {$prefix}core_groups (Name)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('core_tenants', function (Blueprint $table)
        {
            $table->dropIndex(['Name']);
        });
        Capsule::schema()->table('core_groups', function (Blueprint $table)
        {
            $table->dropIndex(['TenantId']);
            $table->dropIndex(['Name']);
        });
        Capsule::schema()->table('core_auth_tokens', function (Blueprint $table)
        {
            $table->dropIndex(['UserId']);
        });
        Capsule::schema()->table('core_min_hashes', function (Blueprint $table)
        {
            $table->dropIndex(['UserId']);
        });
    }
}
