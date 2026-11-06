<?php

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
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        $column = Capsule::connection()->selectOne("SHOW FULL COLUMNS FROM {$prefix}core_users WHERE Field = 'PublicId'");

        if ($column->Collation !== 'utf8mb4_unicode_ci') {
            Capsule::connection()->statement(
                "ALTER TABLE {$prefix}core_users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * The table isn't converted back: the character set it had before isn't known.
     *
     * @return void
     */
    public function down()
    {
    }
}
