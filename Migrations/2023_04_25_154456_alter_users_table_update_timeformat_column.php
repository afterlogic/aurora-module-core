<?php

use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Module as CoreModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class AlterUsersTableUpdateTimeformatColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        User::where('TimeFormat', null)
            ->update([
                'TimeFormat' => CoreModule::getInstance()->getModuleSettings()->TimeFormat
        ]);

        Capsule::schema()->table('core_users', function (Blueprint $table) {
            $table->integer('TimeFormat')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->table('core_users', function (Blueprint $table) {
            $table->integer('TimeFormat')->nullable()->change();
        });
    }
}
