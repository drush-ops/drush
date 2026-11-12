<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drush\Commands\watchdog\WatchdogDeleteCommand;
use Drush\Commands\watchdog\WatchdogListCommand;
use Drush\Commands\watchdog\WatchdogShowCommand;
use Drush\Commands\watchdog\WatchdogShowOneCommand;
use Drush\Exceptions\UserAbortException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the watchdog commands: each command is built from container services
 * and driven with a CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(WatchdogShowCommand::class)]
#[CoversClass(WatchdogShowOneCommand::class)]
#[CoversClass(WatchdogListCommand::class)]
#[CoversClass(WatchdogDeleteCommand::class)]
class WatchdogTest extends KernelTestBase
{
    protected static $modules = ['system', 'user', 'dblog'];

    protected Connection $connection;
    protected ModuleHandlerInterface $moduleHandler;
    protected EntityTypeManagerInterface $entityTypeManager;
    protected LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        $this->installSchema('dblog', ['watchdog']);
        $this->connection = $this->container->get('database');
        $this->moduleHandler = $this->container->get('module_handler');
        $this->entityTypeManager = $this->container->get('entity_type.manager');
        $this->logger = $this->container->get('logger.factory')->get('drush');
    }

    /**
     * Writes a notice, a warning and an alert, plus a notice of another type.
     */
    protected function logMessages(): void
    {
        $this->logger->notice('Unish rocks.');
        $this->logger->warning('Rocking Unish.');
        $this->logger->alert('Beware! Rocks of Unish ahead');
        $this->container->get('logger.factory')->get('other')->notice('Scrub');
    }

    public function testShow(): void
    {
        $this->logMessages();
        $tester = $this->show();
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Unish rocks.', $display);
        $this->assertStringContainsString('Rocking Unish.', $display);
        $this->assertStringContainsString('Beware! Rocks of Unish ahead', $display);
        $this->assertStringContainsString('Scrub', $display);
        // Table headers.
        $this->assertMatchesRegularExpression('/ID\s+\|\s+Date\s+\|\s+Type\s+\|\s+Severity\s+\|\s+Message/', $display);
        $this->assertStringNotContainsString('Hostname', $display);

        // Most recent first, limited by --count.
        $display = $this->show(['--count' => 1])->getDisplay();
        $this->assertStringContainsString('Scrub', $display);
        $this->assertStringNotContainsString('Unish rocks.', $display);
    }

    public function testShowFilters(): void
    {
        $this->logMessages();

        $display = $this->show(['--severity' => 'Notice'])->getDisplay();
        $this->assertStringContainsString('Unish rocks.', $display);
        $this->assertStringContainsString('Notice', $display);
        $this->assertStringNotContainsString('Warning', $display);
        $this->assertStringNotContainsString('Alert', $display);

        $display = $this->show(['--severity-min' => 'Warning'])->getDisplay();
        $this->assertStringNotContainsString('Notice', $display);
        $this->assertStringContainsString('Warning', $display);
        $this->assertStringContainsString('Alert', $display);

        $display = $this->show(['--type' => 'other'])->getDisplay();
        $this->assertStringContainsString('Scrub', $display);
        $this->assertStringNotContainsString('Unish', $display);

        $display = $this->show(['substring' => 'Rocking'])->getDisplay();
        $this->assertStringContainsString('Rocking Unish.', $display);
        $this->assertStringNotContainsString('Unish rocks.', $display);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unrecognized message type: nope.');
        $this->show(['--type' => 'nope']);
    }

    public function testShowFull(): void
    {
        $message = str_repeat('*', 300);
        $this->logger->notice($message);
        // The table truncates long messages unless --full is given.
        $this->assertLessThan(300, substr_count($this->show()->getDisplay(), '*'));
        $this->assertGreaterThanOrEqual(300, substr_count($this->show(['--full' => true])->getDisplay(), '*'));
    }

    public function testShowJson(): void
    {
        $tester = $this->show(['--format' => 'json']);
        $this->assertSame('[]', trim($tester->getDisplay()));
        $this->assertStringContainsString('No log messages available.', $tester->getErrorOutput());

        $this->logMessages();
        $rows = json_decode($this->show(['--format' => 'json', '--type' => 'drush'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(3, $rows);
        $row = end($rows);
        $this->assertSame(key($rows), $row['wid']);
        $this->assertSame('Unish rocks.', $row['message']);
        $this->assertSame('Notice', $row['severity']);
        $this->assertSame('drush', $row['type']);
        $this->assertSame('Anonymous', $row['username']);
        $this->assertArrayHasKey('hostname', $row);

        $rows = json_decode($this->show(['--format' => 'json', '--fields' => 'wid,message'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['wid', 'message'], array_keys(reset($rows)));
    }

    public function testShowOne(): void
    {
        $this->logMessages();
        $wid = $this->widOf('Rocking Unish.');

        $display = $this->show(['--id' => $wid, 'substring' => 'no such message'])->getDisplay();
        $this->assertStringContainsString('Rocking Unish.', $display);
        $this->assertStringNotContainsString('Unish rocks.', $display);
        $this->assertMatchesRegularExpression('/Field\s+\|\s+Value/', $display);
        $this->assertMatchesRegularExpression('/Severity\s+\|\s+Warning/', $display);
        $this->assertStringContainsString('Hostname', $display);
        $this->assertStringContainsString('Location', $display);

        $record = json_decode($this->show(['--id' => $wid, '--format' => 'json'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Rocking Unish.', $record['message']);
        $this->assertSame($wid, (int) $record['wid']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Watchdog message #999 not found.');
        $this->show(['--id' => 999]);
    }

    public function testShowOneDeprecated(): void
    {
        $this->logMessages();
        $wid = $this->widOf('Rocking Unish.');
        $this->expectUserDeprecationMessageMatches('/watchdog:show-one is deprecated/');
        $tester = new CommandTester(new WatchdogShowOneCommand($this->connection, $this->moduleHandler, $this->entityTypeManager));
        $this->assertSame(Command::SUCCESS, $tester->execute(['id' => $wid]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Rocking Unish.', $display);
        $this->assertStringContainsString('deprecated', $display);
        $this->assertStringContainsString("watchdog:show --id=$wid", $display);
    }

    public function testList(): void
    {
        $this->logMessages();
        $tester = new CommandTester(new WatchdogListCommand($this->connection, $this->moduleHandler, $this->entityTypeManager));
        // The types come first in the chooser, so 'other' can be picked by name.
        $tester->setInputs(['other']);
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Select a message type or severity level', $display);
        $this->assertStringContainsString('Scrub', $display);
        $this->assertStringNotContainsString('Unish', $display);

        // A severity label is accepted too.
        $tester->setInputs(['Warning']);
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Rocking Unish.', $display);
        $this->assertStringNotContainsString('Scrub', $display);
    }

    public function testDeleteAll(): void
    {
        $this->logMessages();
        $tester = $this->delete(['substring' => 'all'], ['yes']);
        $this->assertStringContainsString('All watchdog messages have been deleted.', $tester->getDisplay());
        $this->assertSame(0, $this->countRecords());
    }

    public function testDeleteBySubstring(): void
    {
        $this->logMessages();
        $tester = $this->delete(['substring' => 'Rocking'], ['yes']);
        $this->assertStringContainsString('message body containing Rocking', $tester->getDisplay());
        $this->assertStringContainsString('1 watchdog message has been deleted.', $tester->getDisplay());
        $this->assertSame(3, $this->countRecords());
        $this->assertStringNotContainsString('Rocking Unish.', $this->show()->getDisplay());
    }

    public function testDeleteById(): void
    {
        $this->logMessages();
        $wid = $this->widOf('Scrub');
        $tester = $this->delete(['substring' => (string) $wid], ['yes']);
        $this->assertStringContainsString("Watchdog message #$wid has been deleted.", $tester->getDisplay());
        $this->assertSame(3, $this->countRecords());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Watchdog message #$wid does not exist.");
        $this->delete(['substring' => (string) $wid], ['yes']);
    }

    public function testDeleteBySeverity(): void
    {
        $this->logMessages();
        $tester = $this->delete(['--severity' => 'Notice'], ['yes']);
        $this->assertStringContainsString('2 watchdog messages have been deleted.', $tester->getDisplay());
        $this->assertSame(2, $this->countRecords());
        $display = $this->show()->getDisplay();
        $this->assertStringNotContainsString('Notice', $display);
        $this->assertStringContainsString('Rocking Unish.', $display);
    }

    public function testDeleteByType(): void
    {
        $this->logMessages();
        $tester = $this->delete(['--type' => 'other'], ['yes']);
        $this->assertStringContainsString('1 watchdog message has been deleted.', $tester->getDisplay());
        $this->assertSame(3, $this->countRecords());
        $this->assertStringNotContainsString('Scrub', $this->show()->getDisplay());
    }

    public function testDeleteDeclined(): void
    {
        $this->logMessages();
        try {
            $this->delete(['substring' => 'all'], ['no']);
            $this->fail('A declined confirmation did not abort the command.');
        } catch (UserAbortException $e) {
            $this->assertSame('Cancelled.', $e->getMessage());
        }
        $this->assertSame(4, $this->countRecords());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No options provided.');
        $this->delete([], ['yes']);
    }

    protected function show(array $input = []): CommandTester
    {
        $tester = new CommandTester(new WatchdogShowCommand($this->connection, $this->moduleHandler, $this->entityTypeManager));
        $this->assertSame(Command::SUCCESS, $tester->execute($input, ['capture_stderr_separately' => true]));
        return $tester;
    }

    protected function delete(array $input, array $inputs): CommandTester
    {
        $tester = new CommandTester(new WatchdogDeleteCommand($this->connection, $this->moduleHandler));
        $tester->setInputs($inputs);
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    protected function widOf(string $message): int
    {
        return (int) $this->connection->select('watchdog', 'w')
            ->fields('w', ['wid'])
            ->condition('message', $message)
            ->execute()
            ->fetchField();
    }

    protected function countRecords(): int
    {
        return (int) $this->connection->select('watchdog')->countQuery()->execute()->fetchField();
    }
}
