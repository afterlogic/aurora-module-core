<?php

require "../../../system/bootstrap.php";

use \Illuminate\Database\Capsule\Manager as Capsule;

if (!Capsule::schema()->hasTable('users')) {

    Capsule::schema()->create('users', function ($table) {

        $table->increments('Id');
        $table->string('Name')->default('');
        $table->string('PublicId')->unique();
        $table->integer('IdTenant')->default(0);
        $table->boolean('IsDisabled')->default(false);
        $table->integer('IdSubscription')->default(0);
        $table->integer('Role')->default(\Aurora\System\Enums\UserRole::NormalUser);

        $table->datetime('DateCreated')->nullable();
        $table->datetime('LastLogin')->nullable();
        $table->string('LastLoginNow')->default('');
        $table->integer('LoginsCount')->default(0);

        $table->string('Language')->default('');

        $table->integer('TimeFormat')->default(1);
        $table->string('DateFormat')->default('');

        $table->string('Question1')->default('');
        $table->string('Question2')->default('');
        $table->string('Answer1')->default('');
        $table->string('Answer2')->default('');

        $table->boolean('SipEnable')->default(true);
        $table->string('SipImpi')->default('');
        $table->string('SipPassword')->default('');

        $table->boolean('DesktopNotifications')->default(false);

        $table->string('Capa')->default('');
        $table->string('CustomFields')->default('');

        $table->boolean('FilesEnable')->default(true);

        $table->string('EmailNotification')->default('');

        $table->string('PasswordResetHash')->default('');

        $table->boolean('WriteSeparateLog')->default(false);

        $table->integer('TokensValidFromTimestamp')->default(0);

        $table->json('Properties')->nullable();

        $table->timestamps();
    });
}