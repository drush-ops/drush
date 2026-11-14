<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Database\Connection;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\sql\SqlDumpCommand;
use Drush\Doctor\Sql\Client;
use Drush\Doctor\Sql\DumpOptions;
use Drush\Doctor\Sql\TableSelection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Tests the dr-only sql:dump command on whatever driver the kernel suite
 * runs on, plus the mysql and pgsql command lines as strings.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SqlDumpCommand::class)]
#[CoversClass(TableSelection::class)]
class SqlDumpTest extends KernelTestBase
{
    protected Connection $connection;
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->container->get('database');
        $schema = $this->connection->schema();
        foreach (['sqltest_data', 'sqltest_structure', 'sqltest_skip'] as $table) {
            $schema->createTable($table, [
                'fields' => ['id' => ['type' => 'int', 'not null' => true], 'name' => ['type' => 'varchar', 'length' => 32, 'not null' => true]],
                'primary key' => ['id'],
            ]);
            $this->connection->insert($table)->fields(['id' => 1, 'name' => "row_of_$table"])->execute();
        }
        // The kernel's tables carry a prefix, which on sqlite means an attached
        // file that sqlite3 .dump does not see. A real site has unprefixed
        // tables, so one of those stands in for the content assertions.
        $this->connection->query('CREATE TABLE drush_sqltest_raw (id INT NOT NULL, name VARCHAR(32) NOT NULL)');
        $this->connection->query("INSERT INTO drush_sqltest_raw (id, name) VALUES (1, 'row_of_raw_table')");
        $this->dir = sys_get_temp_dir() . '/' . uniqid('drush-sql-dump-');
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->connection->query('DROP TABLE IF EXISTS drush_sqltest_raw');
        array_map(unlink(...), glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    public function testDumpToFile(): void
    {
        $this->requireDumpProgram();
        $file = $this->dir . '/dump.sql';
        $tester = $this->dump(['--result-file' => $file]);
        $this->assertSame("$file\n", $tester->getDisplay());
        // SymfonyStyle wraps long lines, so only the fixed part is asserted.
        $this->assertStringContainsString('Database dump saved to', $tester->getErrorOutput());
        $dump = file_get_contents($file);
        $this->assertStringContainsString('row_of_raw_table', $dump);
        if ($this->connection->driver() !== 'sqlite') {
            $this->assertStringContainsString($this->connection->getPrefix() . 'sqltest_data', $dump);
            $this->assertStringContainsString('row_of_sqltest_data', $dump);
        }

        $json = json_decode($this->dump(['--result-file' => $file, '--format' => 'json'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['path' => $file], $json);
    }

    public function testGzip(): void
    {
        $this->requireDumpProgram();
        if ((new ExecutableFinder())->find('gzip') === null) {
            $this->markTestSkipped('gzip is not installed.');
        }
        $file = $this->dir . '/dump.sql';
        $this->assertSame("$file.gz\n", $this->dump(['--result-file' => $file, '--gzip' => true])->getDisplay());
        $this->assertStringContainsString('row_of_raw_table', gzdecode(file_get_contents("$file.gz")));
    }

    public function testStdout(): void
    {
        $this->requireDumpProgram();
        $tester = $this->dump([]);
        $this->assertStringContainsString('row_of_raw_table', $tester->getDisplay());
        $this->assertStringNotContainsString('Database dump saved', $tester->getErrorOutput());
    }

    public function testAuto(): void
    {
        $this->requireDumpProgram();
        putenv('HOME=' . $this->dir);
        $path = trim($this->dump(['--result-file' => 'auto'])->getDisplay());
        $database = basename($this->connection->getConnectionOptions()['database']);
        $this->assertMatchesRegularExpression('#^' . preg_quote("$this->dir/drush-backups/$database/{$database}_", '#') . '\d{8}_\d{6}\.sql$#', $path);
        $this->assertFileExists($path);
        unlink($path);
        rmdir(dirname($path));
        rmdir(dirname($path, 2));
    }

    public function testTableSelection(): void
    {
        $this->requireDumpProgram();
        if ($this->connection->driver() === 'sqlite') {
            $this->markTestSkipped('sqlite3 .dump has no table selection.');
        }
        $prefix = $this->connection->getPrefix();
        $file = $this->dir . '/dump.sql';
        $this->dump(['--result-file' => $file, '--skip-tables-list' => "{$prefix}sqltest_sk*", '--structure-tables-list' => "{$prefix}sqltest_structure"]);
        $dump = file_get_contents($file);
        $this->assertStringContainsString('row_of_sqltest_data', $dump);
        $this->assertStringNotContainsString('row_of_sqltest_skip', $dump);
        $this->assertStringNotContainsString("{$prefix}sqltest_skip", $dump);
        $this->assertStringContainsString("{$prefix}sqltest_structure", $dump);
        $this->assertStringNotContainsString('row_of_sqltest_structure', $dump);

        $this->dump(['--result-file' => $file, '--tables-list' => "{$prefix}sqltest_skip"]);
        $dump = file_get_contents($file);
        $this->assertStringContainsString('row_of_sqltest_skip', $dump);
        $this->assertStringNotContainsString('row_of_sqltest_data', $dump);
    }

    public function testExpand(): void
    {
        $existing = ['cache', 'cache_bootstrap', 'cache_form', 'users', 'sessions'];
        $this->assertSame(['cache', 'cache_bootstrap', 'cache_form'], TableSelection::expand('cache*', $existing));
        $this->assertSame(['cache_form', 'sessions'], TableSelection::expand('sessions, cache_form, missing', $existing));
        $this->assertSame([], TableSelection::expand(null, $existing));
        $this->assertSame([], TableSelection::expand('', $existing));
        // A dot in a name is literal, not a regex wildcard.
        $this->assertSame([], TableSelection::expand('cache.bootstrap', $existing));
    }

    public function testMysqlCommand(): void
    {
        $client = Client::fromInfo(['driver' => 'mysql', 'username' => 'u', 'password' => 'p', 'host' => 'h', 'port' => 3307, 'database' => 'db']);
        $dump = $client->dumpProgram();
        $this->assertSame("$dump --user=u --host=h --port=3307 db --no-autocommit --single-transaction --opt -Q", $client->dumpCommand(new TableSelection(), new DumpOptions()));
        $this->assertSame(
            "($dump --user=u --host=h --port=3307 db --no-autocommit --single-transaction --opt -Q --ignore-table=db.cache --ignore-table=db.sessions && $dump --user=u --host=h --port=3307 db --no-data --no-autocommit --single-transaction --opt -Q cache)",
            $client->dumpCommand(new TableSelection(skip: ['sessions'], structure: ['cache']), new DumpOptions())
        );
        $this->assertSame("$dump --user=u --host=h --port=3307 db --no-autocommit --single-transaction --opt -Q --no-create-info --skip-extended-insert --order-by-primary --skip-add-drop-table users", $client->dumpCommand(new TableSelection(tables: ['users']), new DumpOptions(dataOnly: true, orderedDump: true, extraDump: '--skip-add-drop-table')));
        $this->assertSame(['MYSQL_PWD' => 'p'], $client->environment());
    }

    public function testPgsqlCommand(): void
    {
        $client = Client::fromInfo(['driver' => 'pgsql', 'username' => 'u', 'password' => 'p', 'host' => 'h', 'database' => 'db']);
        $this->assertSame('pg_dump --dbname=db --host=h --port=5432 --username=u --clean', $client->dumpCommand(new TableSelection(), new DumpOptions()));
        $this->assertSame('pg_dump --dbname=db --host=h --port=5432 --username=u', $client->dumpCommand(new TableSelection(), new DumpOptions(createDb: true)));
        $this->assertSame(
            '(pg_dump --dbname=db --host=h --port=5432 --username=u --clean --exclude-table=cache --exclude-table=sessions && pg_dump --schema-only --table=cache --dbname=db --host=h --port=5432 --username=u --clean)',
            $client->dumpCommand(new TableSelection(skip: ['sessions'], structure: ['cache']), new DumpOptions())
        );
        $this->assertSame('pg_dump --dbname=db --host=h --port=5432 --username=u --data-only --table=users', $client->dumpCommand(new TableSelection(tables: ['users']), new DumpOptions(dataOnly: true)));
        $this->assertSame(['PGPASSWORD' => 'p'], $client->environment());
    }

    protected function requireDumpProgram(): void
    {
        $client = Client::fromInfo($this->connection->getConnectionOptions());
        if ((new ExecutableFinder())->find($client->dumpProgram()) === null) {
            $this->markTestSkipped($client->dumpProgram() . ' is not installed.');
        }
    }

    protected function dump(array $input): CommandTester
    {
        $tester = new CommandTester(new SqlDumpCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => false, 'capture_stderr_separately' => true]), $tester->getDisplay() . $tester->getErrorOutput());
        return $tester;
    }
}
