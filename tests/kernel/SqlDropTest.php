<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Command\Exception\UserAbortException;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\sql\SqlDropCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the dr-only sql:drop command on whatever driver the kernel suite
 * runs on. Each kernel test has its own table prefix (its own attached
 * file on sqlite), so dropping every table is confined to the test.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SqlDropCommand::class)]
class SqlDropTest extends KernelTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $schema = $this->container->get('database')->schema();
        foreach (['sqltest_one', 'sqltest_two'] as $table) {
            $schema->createTable($table, ['fields' => ['id' => ['type' => 'int', 'not null' => true]], 'primary key' => ['id']]);
        }
    }

    public function testDrop(): void
    {
        $schema = $this->container->get('database')->schema();
        $this->assertTrue($schema->tableExists('sqltest_one'));

        $tester = new CommandTester(new SqlDropCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute([], ['interactive' => false, 'capture_stderr_separately' => true]));
        $this->assertMatchesRegularExpression('/Dropped \d+ tables\./', $tester->getErrorOutput());
        $this->assertFalse($schema->tableExists('sqltest_one'));
        $this->assertFalse($schema->tableExists('sqltest_two'));

        // Nothing left: still a success.
        $tester = new CommandTester(new SqlDropCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute([], ['interactive' => false, 'capture_stderr_separately' => true]));
        $this->assertStringContainsString('No tables to drop.', $tester->getErrorOutput());
    }

    public function testAbort(): void
    {
        $tester = new CommandTester(new SqlDropCommand());
        $tester->setInputs(['no']);
        $this->expectException(UserAbortException::class);
        $tester->execute([], ['interactive' => true]);
    }
}
