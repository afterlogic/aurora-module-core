<?php

require "../../../system/bootstrap.php";

use \Illuminate\Database\Capsule\Manager as Capsule;

if (!Capsule::schema()->hasTable('tenants')) {

    Capsule::schema()->create('tenants', function ($table) {

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
        $table->string('LogUrl')->default('');
        $table->string('CalendarNotificationEmailAccount')->default('');
        $table->string('InviteNotificationEmailAccount')->default('');


        $table->json('Properties')->nullable();

        $table->timestamps();
    });
}