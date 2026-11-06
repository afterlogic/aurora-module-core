<?php

namespace Aurora\Modules\Core\Classes;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Converts core_users to utf8mb4 if that is possible without touching user data.
 */
class UsersTableConverter
{
    public const COMMAND = 'php system/bin/console users:convert-charset';

    private const COLLATION = 'utf8mb4_unicode_ci';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return string|null Why the table wasn't converted, null if it is in utf8mb4 now.
     */
    public function convert()
    {
        $table = $this->connection->getTablePrefix() . 'core_users';
        $column = $this->connection->selectOne("SHOW FULL COLUMNS FROM {$table} WHERE Field = 'PublicId'");

        if ($column->Collation === self::COLLATION) {
            return null;
        }

        $collisions = $this->getCollisions($table);
        if (!empty($collisions)) {
            return "{$table} is not converted to utf8mb4: these users would get the same PublicId: "
                . implode('; ', $collisions)
                . '. Rename or remove the extra users and run: ' . self::COMMAND;
        }

        try {
            $this->connection->statement("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE " . self::COLLATION);
        } catch (QueryException $exception) {
            return "{$table} is not converted to utf8mb4: " . $exception->getMessage()
                . '. Fix the cause and run: ' . self::COMMAND;
        }

        return null;
    }

    /**
     * PublicId is unique, and logins which differ in the current collation can be equal in the new one.
     *
     * @param string $table
     *
     * @return array
     */
    private function getCollisions($table)
    {
        $rows = $this->connection->select(
            "SELECT GROUP_CONCAT(PublicId SEPARATOR ', ') AS Logins FROM {$table}
            GROUP BY CONVERT(PublicId USING utf8mb4) COLLATE " . self::COLLATION . '
            HAVING COUNT(*) > 1'
        );

        return array_column($rows, 'Logins');
    }
}
