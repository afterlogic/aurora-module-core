<?php

use Aurora\Modules\Core\Classes\UsersTableConverter;
use Aurora\System\Api;
use Aurora\System\Enums\LogLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

class ConvertCoreUsersTableToUtf8mb4 extends Migration
{
    /**
     * Run the migrations.
     *
     * Contacts are joined with users by adav_addressbooks.principal_email = core_users.PublicId. The index
     * on PublicId can't be used if the columns have different character sets, and then the whole index
     * is read for every contact. adav_addressbooks is already converted to utf8mb4.
     *
     * A table which can't be converted only makes these queries slower, so it doesn't stop the update.
     *
     * @return void
     */
    public function up()
    {
        $problem = (new UsersTableConverter(Capsule::connection()))->convert();

        if ($problem !== null) {
            Api::Log($problem, LogLevel::Error);
            echo 'WARNING: ' . $problem . PHP_EOL;
        }
    }

    /**
     * Reverse the migrations.
     *
     * The table isn't converted back: the character set it had before isn't known.
     *
     * @return void
     */
    public function down() {}
}
