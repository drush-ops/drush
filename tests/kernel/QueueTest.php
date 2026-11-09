<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drush\Commands\queue\QueueDeleteCommand;
use Drush\Commands\queue\QueueListCommand;
use Drush\Commands\queue\QueueRunCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the queue commands the way Drupal core tests its console commands:
 * each command is built from container services and driven with a
 * CommandTester. The woot module provides the queue workers under test.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(QueueListCommand::class)]
#[CoversClass(QueueRunCommand::class)]
#[CoversClass(QueueDeleteCommand::class)]
class QueueTest extends KernelTestBase
{
    protected static $modules = ['system', 'woot'];

    protected QueueWorkerManagerInterface $workerManager;
    protected QueueFactory $queueFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workerManager = $this->container->get('plugin.manager.queue_worker');
        $this->queueFactory = $this->container->get('queue');
    }

    /**
     * An item that throws RequeueException is released and processed again.
     */
    public function testRequeueException(): void
    {
        $this->fillQueue('woot_requeue_exception', [['foo' => 'bar']]);
        $this->assertSame(1, $this->itemCount('woot_requeue_exception'));

        // The worker throws RequeueException the first time it sees the item
        // and records that in state. The command releases the item, claims
        // it again, and the second attempt succeeds.
        $tester = $this->runQueue('woot_requeue_exception');
        $this->assertStringContainsString('Processed 1 items from the woot_requeue_exception queue', $tester->getDisplay());
        $this->assertSame(0, $this->itemCount('woot_requeue_exception'));
    }

    /**
     * An item that throws DelayedRequeueException is delayed, not consumed.
     */
    public function testDelayedRequeueException(): void
    {
        $this->fillQueue('woot_delayed_requeue_exception', [['foo' => 'bar']]);

        // The item is delayed for an hour, so the run processes nothing and
        // the item stays in the queue.
        $tester = $this->runQueue('woot_delayed_requeue_exception');
        $this->assertStringContainsString('Processed 0 items from the woot_delayed_requeue_exception queue', $tester->getDisplay());
        $this->assertSame(1, $this->itemCount('woot_delayed_requeue_exception'));
    }

    /**
     * Any other exception skips the item without stopping the run.
     */
    public function testCustomException(): void
    {
        $this->fillQueue('woot_custom_exception', [['foo' => 'bar'], ['baz' => 'qux']]);
        $this->assertSame(2, $this->itemCount('woot_custom_exception'));

        // The worker throws a CustomException on the first item and records
        // that in state, so the second item succeeds. The first item is left
        // in the queue.
        $tester = $this->runQueue('woot_custom_exception');
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Something went wrong, skip me!', $display);
        $this->assertStringContainsString('Processed 1 items from the woot_custom_exception queue', $display);
        $this->assertSame(1, $this->itemCount('woot_custom_exception'));
    }

    /**
     * A SuspendQueueException stops the run without failing it.
     */
    public function testSuspendQueueException(): void
    {
        $this->fillQueue('woot_suspend_queue_exception', [['foo' => 'bar'], ['baz' => 'qux']]);

        // The first item suspends the queue. The command releases it, warns,
        // stops processing, and still exits 0. Neither item is consumed.
        $tester = $this->runQueue('woot_suspend_queue_exception');
        $this->assertStringContainsString('Suspended the woot_suspend_queue_exception queue', $tester->getDisplay());
        $this->assertSame(2, $this->itemCount('woot_suspend_queue_exception'));
    }

    public function testDelete(): void
    {
        $this->fillQueue('woot_custom_exception', [['foo' => 'bar'], ['baz' => 'qux']]);
        $this->assertSame(2, $this->itemCount('woot_custom_exception'));

        $tester = new CommandTester(new QueueDeleteCommand($this->workerManager, $this->queueFactory));
        $code = $tester->execute(['name' => 'woot_custom_exception']);
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('All items in woot_custom_exception queue deleted.', $tester->getDisplay());
        $this->assertSame(0, $this->itemCount('woot_custom_exception'));
    }

    public function testList(): void
    {
        $this->fillQueue('woot_custom_exception', [['foo' => 'bar'], ['baz' => 'qux']]);
        $this->fillQueue('woot_requeue_exception', []);

        $tester = new CommandTester(new QueueListCommand($this->workerManager, $this->queueFactory));
        $code = $tester->execute([]);
        $this->assertSame(Command::SUCCESS, $code);
        $display = $tester->getDisplay();
        $this->assertMatchesRegularExpression('/Queue\s+\|\s+Items\s+\|\s+Class/', $display);
        $this->assertMatchesRegularExpression('/woot_custom_exception\s+\|\s+2\s+\|\s+Drupal\\\\Core\\\\Queue\\\\DatabaseQueue/', $display);
        $this->assertMatchesRegularExpression('/woot_requeue_exception\s+\|\s+0\s+\|/', $display);

        $tester = new CommandTester(new QueueListCommand($this->workerManager, $this->queueFactory));
        $code = $tester->execute(['--format' => 'json']);
        $this->assertSame(Command::SUCCESS, $code);
        $json = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $json['woot_custom_exception']['items']);
        $this->assertSame(0, $json['woot_requeue_exception']['items']);
        $this->assertSame('Drupal\Core\Queue\DatabaseQueue', $json['woot_custom_exception']['class']);

        $tester = new CommandTester(new QueueListCommand($this->workerManager, $this->queueFactory));
        $code = $tester->execute(['--format' => 'yaml']);
        $this->assertSame(Command::SUCCESS, $code);
        $yaml = Yaml::decode($tester->getDisplay());
        $this->assertSame(2, $yaml['woot_custom_exception']['items']);
    }

    public function testUnknownQueue(): void
    {
        foreach ([QueueRunCommand::class, QueueDeleteCommand::class] as $class) {
            $tester = new CommandTester(new $class($this->workerManager, $this->queueFactory));
            try {
                $tester->execute(['name' => 'no_such_queue']);
                $this->fail("$class accepted an unknown queue name.");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Queue not found: no_such_queue', $e->getMessage());
            }
        }
    }

    /**
     * Runs queue:run against the named queue and asserts it exited 0.
     */
    protected function runQueue(string $name): CommandTester
    {
        $tester = new CommandTester(new QueueRunCommand($this->workerManager, $this->queueFactory));
        $code = $tester->execute(['name' => $name]);
        $this->assertSame(Command::SUCCESS, $code, $tester->getDisplay());
        return $tester;
    }

    /**
     * Creates the named queue and fills it with the given items.
     */
    protected function fillQueue(string $name, array $items): void
    {
        $queue = $this->queueFactory->get($name, true);
        $queue->createQueue();
        foreach ($items as $item) {
            $queue->createItem($item);
        }
    }

    protected function itemCount(string $name): int
    {
        return $this->queueFactory->get($name)->numberOfItems();
    }
}
