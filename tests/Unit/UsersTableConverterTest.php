<?php

namespace Aurora\Modules\Core\Tests\Unit;

use Aurora\Modules\Core\Classes\UsersTableConverter;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/Classes/UsersTableConverter.php';

class UsersTableConverterTest extends TestCase
{
    private function connection($collation, array $collisions = [])
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getTablePrefix')->willReturn('au_');
        $connection->method('selectOne')->willReturn((object) ['Collation' => $collation]);
        $connection->method('select')->willReturn(array_map(function ($logins) {
            return (object) ['Logins' => $logins];
        }, $collisions));

        return $connection;
    }

    public function testConvertedTableIsNotTouched()
    {
        $connection = $this->connection('utf8mb4_unicode_ci');
        $connection->expects($this->never())->method('statement');

        $this->assertNull((new UsersTableConverter($connection))->convert());
    }

    public function testTableWithoutCollisionsIsConverted()
    {
        $connection = $this->connection('utf8mb3_general_ci');
        $connection->expects($this->once())->method('statement')
            ->with($this->stringContains('ALTER TABLE au_core_users CONVERT TO CHARACTER SET utf8mb4'));

        $this->assertNull((new UsersTableConverter($connection))->convert());
    }

    public function testCollisionsAreReportedAndTableIsNotAltered()
    {
        $connection = $this->connection('utf8mb3_general_ci', ['first@example.com, second@example.com']);
        $connection->expects($this->never())->method('statement');

        $problem = (new UsersTableConverter($connection))->convert();

        $this->assertStringContainsString('first@example.com, second@example.com', $problem);
        $this->assertStringContainsString(UsersTableConverter::COMMAND, $problem);
    }

    public function testFailedAlterIsReportedInsteadOfThrown()
    {
        $connection = $this->connection('utf8mb3_general_ci');
        $connection->method('statement')->willThrowException(
            new QueryException('ALTER', [], new \Exception('Index column size too large'))
        );

        $problem = (new UsersTableConverter($connection))->convert();

        $this->assertStringContainsString('Index column size too large', $problem);
        $this->assertStringContainsString(UsersTableConverter::COMMAND, $problem);
    }
}
