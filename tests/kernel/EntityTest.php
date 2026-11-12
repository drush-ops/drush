<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drush\Commands\entity\EntityDeleteCommand;
use Drush\Commands\entity\EntitySaveCommand;
use Drush\Exceptions\UserAbortException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the entity commands: each command is built from container services
 * and driven with a CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(EntityDeleteCommand::class)]
#[CoversClass(EntitySaveCommand::class)]
class EntityTest extends KernelTestBase
{
    protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter'];

    protected EntityTypeManagerInterface $entityTypeManager;
    protected TimeInterface $time;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        $this->installEntitySchema('node');
        $this->installSchema('node', ['node_access']);
        $this->installSchema('user', ['users_data']);
        $this->installConfig(['node', 'filter']);
        NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
        NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
        $this->entityTypeManager = $this->container->get('entity_type.manager');
        $this->time = $this->container->get('datetime.time');
    }

    public function testDeleteByIds(): void
    {
        $this->createNodes(3);
        // No prompt is shown when ids are given, so a non-interactive run succeeds.
        $tester = $this->delete(['entity-type' => 'node', 'ids' => '1,3'], [], false);
        $this->assertStringContainsString('Deleted node entity Ids: 1, 3', $tester->getDisplay());
        $this->assertSame([2], $this->nodeIds());
    }

    public function testDeleteByBundle(): void
    {
        $this->createNodes(2, 'article');
        $this->createNodes(2, 'page');
        $tester = $this->delete(['entity-type' => 'node', '--bundle' => 'article'], ['yes']);
        $this->assertStringContainsString('You are about to delete 2 entities. Do you wish to continue?', $tester->getDisplay());
        $this->assertStringContainsString('Deleted node entity Ids: 1, 2', $tester->getDisplay());
        $this->assertSame([3, 4], $this->nodeIds());
    }

    public function testDeleteDeclined(): void
    {
        $this->createNodes(2);
        try {
            $this->delete(['entity-type' => 'node'], ['no']);
            $this->fail('A declined confirmation did not abort the command.');
        } catch (UserAbortException $e) {
            $this->assertSame('Cancelled.', $e->getMessage());
        }
        $this->assertSame([1, 2], $this->nodeIds());
    }

    public function testDeleteExclude(): void
    {
        $this->createNodes(4);
        $tester = $this->delete(['entity-type' => 'node', '--exclude' => '2,3'], ['yes']);
        $this->assertStringContainsString('Deleted node entity Ids: 1, 4', $tester->getDisplay());
        $this->assertSame([2, 3], $this->nodeIds());
    }

    public function testDeleteLimitSkipsConfirm(): void
    {
        $this->createNodes(3);
        $tester = $this->delete(['entity-type' => 'node', '--limit' => '2'], [], false);
        $this->assertStringNotContainsString('Do you wish to continue?', $tester->getDisplay());
        $this->assertStringContainsString('Deleted node entity Ids: 1, 2', $tester->getDisplay());
        $this->assertSame([3], $this->nodeIds());
    }

    public function testDeleteNoMatches(): void
    {
        $tester = $this->delete(['entity-type' => 'node', '--bundle' => 'article'], [], false);
        $this->assertStringContainsString('No matching entities found.', $tester->getDisplay());
    }

    public function testDeleteUsersKeepsAdmin(): void
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $storage->create(['uid' => 0, 'name' => '', 'status' => 0])->save();
        $storage->create(['name' => 'admin', 'status' => 1])->save();
        $storage->create(['name' => 'bob', 'status' => 1])->save();
        $tester = $this->delete(['entity-type' => 'user'], ['yes']);
        $this->assertStringContainsString('Deleted user entity Ids: 2', $tester->getDisplay());
        $this->assertSame([0, 1], array_map('intval', array_keys($storage->loadMultiple())));
    }

    public function testDeleteUnknownEntityType(): void
    {
        try {
            $this->delete(['entity-type' => 'no_such_type'], [], false);
            $this->fail('An unknown entity type was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Unknown entity type: no_such_type', $e->getMessage());
        }
    }

    public function testSaveByIds(): void
    {
        [$node] = $this->createNodes(2);
        $node->setChangedTime(1000)->save();
        $revisionId = (int) $node->getRevisionId();

        $tester = $this->save(['entity-type' => 'node', 'ids' => '1']);
        $this->assertStringContainsString('Saved node entity ids: 1', $tester->getDisplay());
        $this->assertStringNotContainsString('Entities have been', $tester->getDisplay());

        $node = $this->loadNode(1);
        $this->assertSame($this->time->getRequestTime(), $node->getChangedTime());
        $this->assertGreaterThan($revisionId, (int) $node->getRevisionId());
        $this->assertSame('Re-saved by Drush entity:save. ', $node->getRevisionLogMessage());
        // The other node is left alone.
        $this->assertSame(2, (int) $this->loadNode(2)->getRevisionId());
    }

    public function testSavePublishUnpublish(): void
    {
        $this->createNodes(2);
        $tester = $this->save(['entity-type' => 'node', '--unpublish' => true]);
        $this->assertStringContainsString('Saved node entity ids: 1, 2', $tester->getDisplay());
        $this->assertStringContainsString('Entities have been unpublished.', $tester->getDisplay());
        $this->assertFalse($this->loadNode(1)->isPublished());
        $this->assertFalse($this->loadNode(2)->isPublished());

        $tester = $this->save(['entity-type' => 'node', 'ids' => '2', '--publish' => true]);
        $this->assertStringContainsString('Entities have been published.', $tester->getDisplay());
        $this->assertFalse($this->loadNode(1)->isPublished());
        $this->assertTrue($this->loadNode(2)->isPublished());
    }

    public function testSaveConflictingOptions(): void
    {
        $this->createNodes(1);
        $cases = [
            [['--publish' => true, '--unpublish' => true], 'You may not specify both --publish and --unpublish.'],
            [['--state' => 'draft', '--publish' => true], 'You may not specify both --state and --publish.'],
            [['--state' => 'draft', '--unpublish' => true], 'You may not specify both --state and --unpublish.'],
        ];
        foreach ($cases as [$options, $message]) {
            try {
                $this->save(['entity-type' => 'node'] + $options);
                $this->fail('Conflicting options were accepted: ' . $message);
            } catch (\InvalidArgumentException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    public function testSaveStateWithoutModeration(): void
    {
        $this->createNodes(1);
        try {
            $this->save(['entity-type' => 'node', '--state' => 'draft']);
            $this->fail('A moderation state was accepted without content moderation.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('article 1 does not support content moderation.', $e->getMessage());
        }
    }

    public function testSaveIdsFromStdin(): void
    {
        $this->createNodes(3);
        $tester = $this->save(['entity-type' => 'node', 'ids' => '-'], ["1,\n3,\n"]);
        $this->assertStringContainsString('Saved node entity ids: 1, 3', $tester->getDisplay());
        $this->assertGreaterThan(1, (int) $this->loadNode(1)->getRevisionId());
        $this->assertSame(2, (int) $this->loadNode(2)->getRevisionId());
        $this->assertGreaterThan(3, (int) $this->loadNode(3)->getRevisionId());
    }

    public function testSaveUnknownEntityType(): void
    {
        try {
            $this->save(['entity-type' => 'no_such_type']);
            $this->fail('An unknown entity type was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Unknown entity type: no_such_type', $e->getMessage());
        }
    }

    /**
     * Runs entity:delete and asserts it succeeded.
     */
    protected function delete(array $input, array $inputs = [], bool $interactive = true): CommandTester
    {
        $tester = new CommandTester(new EntityDeleteCommand($this->entityTypeManager));
        $tester->setInputs($inputs);
        $this->assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => $interactive]));
        return $tester;
    }

    /**
     * Runs entity:save and asserts it succeeded.
     */
    protected function save(array $input, array $inputs = []): CommandTester
    {
        $command = new EntitySaveCommand(
            $this->entityTypeManager,
            $this->time,
            $this->container->get('current_user'),
        );
        $tester = new CommandTester($command);
        $tester->setInputs($inputs);
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    /**
     * @return NodeInterface[]
     */
    protected function createNodes(int $count, string $type = 'article'): array
    {
        $nodes = [];
        for ($i = 0; $i < $count; $i++) {
            $node = Node::create(['type' => $type, 'title' => "$type $i"]);
            $node->save();
            $nodes[] = $node;
        }
        return $nodes;
    }

    protected function loadNode(int $nid): NodeInterface
    {
        $storage = $this->entityTypeManager->getStorage('node');
        $storage->resetCache([$nid]);
        $node = $storage->load($nid);
        $this->assertInstanceOf(NodeInterface::class, $node);
        return $node;
    }

    /**
     * @return int[]
     */
    protected function nodeIds(): array
    {
        $ids = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(false)->sort('nid')->execute();
        return array_map('intval', array_values($ids));
    }
}
