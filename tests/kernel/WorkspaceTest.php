<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drush\Commands\core\WorkspacePublishCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests workspace:publish: the command is built from container services and
 * driven with a CommandTester against a stage workspace holding a node.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(WorkspacePublishCommand::class)]
class WorkspaceTest extends KernelTestBase
{
    use ContentTypeCreationTrait;
    use NodeCreationTrait;
    use UserCreationTrait;

    protected static $modules = ['system', 'user', 'workspaces', 'node', 'field', 'text', 'filter'];

    protected EntityTypeManagerInterface $entityTypeManager;
    protected WorkspaceManagerInterface $workspaceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        $this->installEntitySchema('node');
        $this->installEntitySchema('workspace');
        $this->installSchema('node', ['node_access']);
        $this->installSchema('workspaces', ['workspace_association', 'workspace_association_revision']);
        $this->installConfig(['filter', 'node', 'system']);
        $this->createContentType(['type' => 'page']);
        // The first user is uid 1 and may view and publish any workspace.
        $this->setUpCurrentUser();

        $this->entityTypeManager = $this->container->get('entity_type.manager');
        $this->workspaceManager = $this->container->get('workspaces.manager');
        Workspace::create(['id' => 'stage', 'label' => 'Stage'])->save();
    }

    public function testNothingToPublish(): void
    {
        $tester = $this->publish('stage');
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('There are no changes that can be published from Stage to Live', $tester->getDisplay());
    }

    public function testPublish(): void
    {
        // Create a node while stage is the active workspace.
        $this->workspaceManager->setActiveWorkspace($this->entityTypeManager->getStorage('workspace')->load('stage'));
        $nid = $this->createNode(['type' => 'page', 'title' => 'Staged', 'status' => 1])->id();
        $this->workspaceManager->switchToLive();

        // Live only holds an unpublished placeholder revision until publishing.
        $this->assertFalse($this->loadNode($nid)->isPublished());

        $tester = $this->publish('stage');
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Workspace Stage published', $tester->getDisplay());
        $this->assertTrue($this->loadNode($nid)->isPublished());

        // A second run finds nothing left to publish.
        $this->assertStringContainsString('There are no changes that can be published', $this->publish('stage')->getDisplay());
    }

    public function testUnknownWorkspace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workspace nope not found.');
        $this->publish('nope');
    }

    public function testWorkspacesModuleMissing(): void
    {
        $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
        $moduleHandler->method('moduleExists')->willReturn(false);
        $command = new WorkspacePublishCommand($this->entityTypeManager, $moduleHandler);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The workspaces module is not installed.');
        (new CommandTester($command))->execute(['id' => 'stage']);
    }

    public function testCompletion(): void
    {
        $command = WorkspacePublishCommand::create($this->container);
        $suggestions = $command->suggestWorkspaceIds(CompletionInput::fromTokens([], 0));
        $this->assertSame(['stage'], $suggestions);
    }

    protected function publish(string $id): CommandTester
    {
        $command = new WorkspacePublishCommand(
            $this->entityTypeManager,
            $this->container->get('module_handler'),
            $this->container->get('workspaces.operation_factory'),
        );
        $tester = new CommandTester($command);
        $tester->execute(['id' => $id]);
        return $tester;
    }

    protected function loadNode(int|string $nid): NodeInterface
    {
        $storage = $this->entityTypeManager->getStorage('node');
        $storage->resetCache([$nid]);
        return $storage->load($nid);
    }
}
