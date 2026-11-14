<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\sql\SqlQueryCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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

    public function testEmptyQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The query is empty.');
        $this->query(['query' => '  ']);
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
