<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('core_tenants', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('IdChannel')->default(0);
            $table->boolean('IsDisabled')->default(false);
            $table->boolean('IsDefault')->default(false);
            $table->string('Name')->default('');
            $table->string('Description')->default('');
            $table->string('WebDomain')->default('');
            $table->integer('UserCountLimit')->default(0);
            $table->string('Capa')->default('');
            $table->boolean('AllowChangeAdminEmail')->default(true);
            $table->boolean('AllowChangeAdminPassword')->default(true);
            $table->integer('Expared')->default(0);
            $table->string('PayUrl')->default('');
            $table->boolean('IsTrial')->default(false);
            $table->string('LogoUrl')->default('');
            $table->string('CalendarNotificationEmailAccount')->default('');
            $table->string('InviteNotificationEmailAccount')->default('');


            $table->json('Properties')->nullable();

            $table->timestamp(\Aurora\System\Classes\Model::CREATED_AT)->nullable();
            $table->timestamp(\Aurora\System\Classes\Model::UPDATED_AT)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('core_tenants');
    }
}
