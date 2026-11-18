<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Command\Exception\UserAbortException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drush\Commands\entity\EntityDeleteCommand;
use Drush\Commands\entity\EntitySaveCommand;
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
#[ShareEnvironment]
#[CoversClass(EntityDeleteCommand::class)]
#[CoversClass(EntitySaveCommand::class)]
class EntityTest extends KernelTestBase
{
    protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter'];

    protected EntityTypeManagerInterface $entityTypeManager;
    protected TimeInterface $time;

    protected function setUpEnvironment(): void
    {
        $this->installEntitySchema('user');
        $this->installEntitySchema('node');
        $this->installSchema('node', ['node_access']);
        $this->installSchema('user', ['users_data']);
        $this->installConfig(['node', 'filter']);
        NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
        NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityTypeManager = $this->container->get('entity_type.manager');
        $this->time = $this->container->get('datetime.time');
    }

    /**
     * Every test method starts without content. Ids keep counting up across
     * test methods, so they are read from the entities.
     */
    protected function resetEnvironment(): void
    {
        parent::resetEnvironment();
        foreach (['node', 'user'] as $entityTypeId) {
            $storage = $this->entityTypeManager->getStorage($entityTypeId);
            $storage->delete($storage->loadMultiple());
        }
    }

    public function testDeleteByIds(): void
    {
        [$a, $b, $c] = $this->createNodeIds(3);
        // No prompt is shown when ids are given, so a non-interactive run succeeds.
        $tester = $this->delete(['entity-type' => 'node', 'ids' => "$a,$c"], [], false);
        $this->assertStringContainsString("Deleted node entity Ids: $a, $c", $tester->getDisplay());
        $this->assertSame([$b], $this->nodeIds());
    }

    public function testDeleteByBundle(): void
    {
        $articles = $this->createNodeIds(2, 'article');
        $pages = $this->createNodeIds(2, 'page');
        $tester = $this->delete(['entity-type' => 'node', '--bundle' => 'article'], ['yes']);
        $this->assertStringContainsString('You are about to delete 2 entities. Do you wish to continue?', $tester->getDisplay());
        $this->assertStringContainsString('Deleted node entity Ids: ' . implode(', ', $articles), $tester->getDisplay());
        $this->assertSame($pages, $this->nodeIds());
    }

    public function testDeleteDeclined(): void
    {
        $ids = $this->createNodeIds(2);
        try {
            $this->delete(['entity-type' => 'node'], ['no']);
            $this->fail('A declined confirmation did not abort the command.');
        } catch (UserAbortException $e) {
            $this->assertSame('Operation cancelled by user.', $e->getMessage());
        }
        $this->assertSame($ids, $this->nodeIds());
    }

    public function testDeleteExclude(): void
    {
        [$a, $b, $c, $d] = $this->createNodeIds(4);
        $tester = $this->delete(['entity-type' => 'node', '--exclude' => "$b,$c"], ['yes']);
        $this->assertStringContainsString("Deleted node entity Ids: $a, $d", $tester->getDisplay());
        $this->assertSame([$b, $c], $this->nodeIds());
    }

    public function testDeleteLimitSkipsConfirm(): void
    {
        [$a, $b, $c] = $this->createNodeIds(3);
        $tester = $this->delete(['entity-type' => 'node', '--limit' => '2'], [], false);
        $this->assertStringNotContainsString('Do you wish to continue?', $tester->getDisplay());
        $this->assertStringContainsString("Deleted node entity Ids: $a, $b", $tester->getDisplay());
        $this->assertSame([$c], $this->nodeIds());
    }

    public function testDeleteNoMatches(): void
    {
        $tester = $this->delete(['entity-type' => 'node', '--bundle' => 'article'], [], false);
        $this->assertStringContainsString('No matching entities found.', $tester->getDisplay());
    }

    public function testDeleteUsersKeepsAdmin(): void
    {
        $storage = $this->entityTypeManager->getStorage('user');
        // Explicit uids: the sequence behind an auto-assigned uid differs per
        // database once uid 0 has been inserted by hand.
        $storage->create(['uid' => 0, 'name' => '', 'status' => 0])->save();
        $storage->create(['uid' => 1, 'name' => 'admin', 'status' => 1])->save();
        $storage->create(['uid' => 2, 'name' => 'bob', 'status' => 1])->save();
        $storage->create(['uid' => 3, 'name' => 'carol', 'status' => 1])->save();
        $tester = $this->delete(['entity-type' => 'user'], ['yes']);
        $this->assertStringContainsString('Deleted user entity Ids: 2, 3', $tester->getDisplay());
        $remaining = array_map('intval', array_keys($storage->loadMultiple()));
        sort($remaining);
        $this->assertSame([0, 1], $remaining);
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
        [$node, $other] = $this->createNodes(2);
        $node->setChangedTime(1000)->save();
        $nid = (int) $node->id();
        $revisionId = (int) $node->getRevisionId();
        $otherRevisionId = (int) $other->getRevisionId();

        $tester = $this->save(['entity-type' => 'node', 'ids' => (string) $nid]);
        $this->assertStringContainsString("Saved node entity ids: $nid", $tester->getDisplay());
        $this->assertStringNotContainsString('Entities have been', $tester->getDisplay());

        $node = $this->loadNode($nid);
        $this->assertSame($this->time->getRequestTime(), $node->getChangedTime());
        $this->assertGreaterThan($revisionId, (int) $node->getRevisionId());
        $this->assertSame('Re-saved by Drush entity:save. ', $node->getRevisionLogMessage());
        // The other node is left alone.
        $this->assertSame($otherRevisionId, (int) $this->loadNode((int) $other->id())->getRevisionId());
    }

    public function testSavePublishUnpublish(): void
    {
        [$a, $b] = $this->createNodeIds(2);
        $tester = $this->save(['entity-type' => 'node', '--unpublish' => true]);
        $this->assertStringContainsString("Saved node entity ids: $a, $b", $tester->getDisplay());
        $this->assertStringContainsString('Entities have been unpublished.', $tester->getDisplay());
        $this->assertFalse($this->loadNode($a)->isPublished());
        $this->assertFalse($this->loadNode($b)->isPublished());

        $tester = $this->save(['entity-type' => 'node', 'ids' => (string) $b, '--publish' => true]);
        $this->assertStringContainsString('Entities have been published.', $tester->getDisplay());
        $this->assertFalse($this->loadNode($a)->isPublished());
        $this->assertTrue($this->loadNode($b)->isPublished());
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
        [$nid] = $this->createNodeIds(1);
        try {
            $this->save(['entity-type' => 'node', '--state' => 'draft']);
            $this->fail('A moderation state was accepted without content moderation.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame("article $nid does not support content moderation.", $e->getMessage());
        }
    }

    public function testSaveIdsFromStdin(): void
    {
        [$a, $b, $c] = $nodes = $this->createNodes(3);
        $tester = $this->save(['entity-type' => 'node', 'ids' => '-'], ["{$a->id()},\n{$c->id()},\n"]);
        $this->assertStringContainsString("Saved node entity ids: {$a->id()}, {$c->id()}", $tester->getDisplay());
        $revisionIds = array_map(fn(NodeInterface $node): int => (int) $this->loadNode((int) $node->id())->getRevisionId(), $nodes);
        $this->assertGreaterThan((int) $a->getRevisionId(), $revisionIds[0]);
        $this->assertSame((int) $b->getRevisionId(), $revisionIds[1]);
        $this->assertGreaterThan((int) $c->getRevisionId(), $revisionIds[2]);
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

    /**
     * @return int[]
     */
    protected function createNodeIds(int $count, string $type = 'article'): array
    {
        return array_map(fn(NodeInterface $node): int => (int) $node->id(), $this->createNodes($count, $type));
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
