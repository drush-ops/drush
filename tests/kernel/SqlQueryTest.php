<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\sql\SqlQueryCommand;
use Drush\Doctor\Sql\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Tests the dr-only sql:query command on whatever driver the kernel suite
 * runs on.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SqlQueryCommand::class)]
class SqlQueryTest extends KernelTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $schema = $this->container->get('database')->schema();
        $schema->createTable('sqltest', [
            'fields' => [
                'id' => ['type' => 'int', 'not null' => true],
                'name' => ['type' => 'varchar', 'length' => 32, 'not null' => true],
            ],
            'primary key' => ['id'],
        ]);
        $this->container->get('database')->insert('sqltest')->fields(['id' => 1, 'name' => 'b'])->execute();
        $this->container->get('database')->insert('sqltest')->fields(['id' => 2, 'name' => 'a'])->execute();
    }

    public function testInlineQuery(): void
    {
        $this->assertSame("a\nb\n", $this->query(['query' => 'SELECT name FROM {sqltest} ORDER BY name'])->getDisplay());
        $this->assertSame("1\tb\n2\ta\n", $this->query(['query' => 'SELECT id, name FROM {sqltest} ORDER BY id'])->getDisplay());

        $json = json_decode($this->query(['query' => 'SELECT id, name FROM {sqltest} ORDER BY id', '--format' => 'json'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['id' => '1', 'name' => 'b'], array_map(strval(...), $json[0]));

        // A statement that returns no rows prints nothing.
        $this->assertSame('', $this->query(['query' => "DELETE FROM {sqltest} WHERE name = 'a'"])->getDisplay());
        $this->assertSame("b\n", $this->query(['query' => 'SELECT name FROM {sqltest}'])->getDisplay());
    }

    public function testResultFile(): void
    {
        $file = $this->siteDirectory . '/result.txt';
        $this->assertSame('', $this->query(['query' => 'SELECT name FROM {sqltest} ORDER BY name', '--result-file' => $file])->getDisplay());
        $this->assertSame("a\nb\n", file_get_contents($file));
    }

    public function testFile(): void
    {
        $connection = $this->container->get('database');
        $client = Client::fromInfo($connection->getConnectionOptions());
        if ((new ExecutableFinder())->find($client->program()) === null) {
            $this->markTestSkipped($client->program() . ' is not installed.');
        }
        // A raw SQL file cannot address a prefixed table (on sqlite the prefix
        // is an attached file the client does not know), so this works on an
        // unprefixed table, as a real site has, cleaned up afterwards.
        $table = 'drush_sqltest_file';
        $dir = sys_get_temp_dir() . '/' . uniqid('drush-sql-query-');
        mkdir($dir);
        file_put_contents("$dir/one.sql", "CREATE TABLE $table (id INT NOT NULL, name VARCHAR(32) NOT NULL);\nINSERT INTO $table (id, name) VALUES (3, 'c');\n");
        file_put_contents("$dir/two.sql.gz", gzencode("INSERT INTO $table (id, name) VALUES (4, 'd');\n"));
        try {
            $this->query(['--file' => "$dir/one.sql"]);
            $this->query(['--file' => "$dir/two.sql.gz", '--file-delete' => true]);
            $this->assertFileExists("$dir/one.sql");
            $this->assertFileDoesNotExist("$dir/two.sql.gz");
            $this->assertSame(['c', 'd'], $connection->query("SELECT name FROM $table ORDER BY name")->fetchCol());

            // The client's own output can be captured.
            file_put_contents("$dir/select.sql", "SELECT name FROM $table WHERE id = 3;\n");
            $this->query(['--file' => "$dir/select.sql", '--result-file' => "$dir/out.txt"]);
            $this->assertStringContainsString('c', file_get_contents("$dir/out.txt"));
        } finally {
            $connection->query("DROP TABLE IF EXISTS $table");
            array_map(unlink(...), glob("$dir/*") ?: []);
            rmdir($dir);
        }
    }

    public function testMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $this->query(['--file' => '/nonexistent/file.sql']);
    }

    public function testNoQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pass a query, or --file.');
        $this->query([]);
    }

    public function testBadQuery(): void
    {
        $this->expectException(\Exception::class);
        $this->query(['query' => 'SELECT nothing FROM {no_such_table}']);
    }

    public function testDbUrl(): void
    {
        // The test connection, re-selected through its URL.
        $url = Database::getConnectionInfoAsUrl();
        $display = $this->query(['query' => 'SELECT 1 AS one', '--db-url' => $url])->getDisplay();
        $this->assertSame("1\n", $display);
    }

    protected function query(array $input): CommandTester
    {
        $tester = new CommandTester(new SqlQueryCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input), $tester->getDisplay());
        return $tester;
    }
}
