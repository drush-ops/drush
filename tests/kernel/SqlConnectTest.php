<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\sql\SqlConnectCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the dr-only sql:connect command.
 *
 * The default connection is whatever the suite runs on; specific drivers are
 * covered through --db-url, which needs no server.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SqlConnectCommand::class)]
class SqlConnectTest extends KernelTestBase
{
    public function testDefaultConnection(): void
    {
        $info = Database::getConnectionInfo()['default'];
        $display = $this->connect()->getDisplay();
        $this->assertStringContainsString($info['database'], $display);
        $this->assertSame(rtrim($display) . " -bail\n", $this->connect(['--extra' => '-bail'])->getDisplay());
        if ($info['driver'] === 'sqlite') {
            $this->assertSame("sqlite3 {$info['database']}\n", $display);
        }
    }

    public function testMysqlUrl(): void
    {
        $display = $this->connect(['--db-url' => 'mysql://u:p@h:3307/db'])->getDisplay();
        $this->assertMatchesRegularExpression("/^(mysql|mariadb) --user=u --password=p --database=db --host=h --port=3307\n$/", $display);
    }

    public function testFormat(): void
    {
        $info = Database::getConnectionInfo()['default'];
        $json = json_decode($this->connect(['--format' => 'json'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($info['driver'], $json['driver']);
        $this->assertSame($info['database'], $json['database']);

        $yaml = Yaml::decode($this->connect(['--format' => 'yaml', '--db-url' => 'mysql://u:p@h:3307/db'])->getDisplay());
        $this->assertSame(['driver' => 'mysql', 'username' => 'u', 'password' => 'p', 'host' => 'h', 'database' => 'db', 'port' => 3307], array_intersect_key($yaml, array_flip(['driver', 'username', 'password', 'host', 'database', 'port'])));
    }

    public function testPgsqlUrl(): void
    {
        $display = $this->connect(['--db-url' => 'pgsql://u:secret@h/db'])->getDisplay();
        $this->assertSame("psql -q ON_ERROR_STOP=1 --dbname=db --host=h --port=5432 --username=u\n", $display);
    }

    /**
     * Only values the shell would mangle are quoted, so that
     * `$(dr sql:connect)` works without eval for the common case.
     */
    public function testQuoting(): void
    {
        Database::addConnectionInfo('quoted', 'default', [
            'driver' => 'mysql',
            'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
            'autoload' => '',
            'database' => 'db',
            'username' => 'u',
            'password' => 'p a$s',
            'host' => 'h',
            'unix_socket' => '/var/run/mysqld/mysqld.sock',
        ]);
        $display = $this->connect(['--database' => 'quoted'])->getDisplay();
        $this->assertMatchesRegularExpression("/^(mysql|mariadb) --user=u --password='p a\\\$s' --database=db --socket=\/var\/run\/mysqld\/mysqld.sock\n$/", $display);
    }

    public function testUnknownConnection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database connection nope is not defined.');
        $this->connect(['--database' => 'nope']);
    }

    public function testUnknownTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database connection default has no target replica.');
        $this->connect(['--target' => 'replica']);
    }

    public function testUnknownDriver(): void
    {
        Database::addConnectionInfo('other', 'default', ['driver' => 'oracle', 'namespace' => 'Drupal\\oracle\\Driver\\Database\\oracle', 'autoload' => '', 'database' => 'db']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No database client is known for the oracle driver.');
        $this->connect(['--database' => 'other']);
    }

    protected function connect(array $input = []): CommandTester
    {
        $tester = new CommandTester(new SqlConnectCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input), $tester->getDisplay());
        return $tester;
    }
}
