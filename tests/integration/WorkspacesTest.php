<?php

declare(strict_types=1);

namespace Unish;

use Drupal\node\Entity\Node;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drush\Commands\core\WorkspacesCommand;
use Drush\Commands\pm\PmInstallCommand;

/**
 * Tests Workspaces commands
 *
 * @group commands
 */
class WorkspacesTest extends UnishIntegrationTestCase
{
    private WorkspaceManagerInterface $workspaceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->drush(PmInstallCommand::NAME, ['workspaces', 'node']);
        $this->workspaceManager = \Drupal::service('workspaces.manager');

        // Create article content type if it doesn't exist
        $node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
        if (!isset($node_types['article'])) {
            $article_type = \Drupal\node\Entity\NodeType::create([
                'type' => 'article',
                'name' => 'Article',
            ]);
            $article_type->save();
        }

        // Create a test workspace if it doesn't exist
        $workspace_storage = \Drupal::entityTypeManager()->getStorage('workspace');
        $existing_workspace = $workspace_storage->load('stage');
        if (!$existing_workspace) {
            $workspace = \Drupal\workspaces\Entity\Workspace::create([
                'id' => 'stage',
                'label' => 'Stage',
            ]);
            $workspace->save();
        }

        // Set the stage workspace as active and create content in it
        $stage_workspace = $workspace_storage->load('stage');
        $this->workspaceManager->setActiveWorkspace($stage_workspace);

        // Create a sample node in the workspace context
        $node = Node::create([
            'type' => 'article',
            'title' => 'Test Node in Workspace',
            'body' => 'This is a test node created in the stage workspace.',
            'status' => 1,
        ]);
        $node->save();
    }

    public function testWorkspaces(): void
    {
        // Test that workspace has content to publish
        $workspace_id = 'stage';

        // Publish the workspace
        $this->drush(WorkspacesCommand::NAME, [$workspace_id]);
        $this->assertStringContainsString('Workspace Stage published', $this->getErrorOutput());

        // Verify no more changes exist after publishing
        $this->drush(WorkspacesCommand::NAME, [$workspace_id]);
        $this->assertStringContainsString('There are no changes that can be published', $this->getErrorOutput());
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        // Cleanup the test workspace (this should also handle content deletion)
        $this->drush('entity:delete', ['workspace', 'stage'], ['yes' => true]);
        $this->drush('entity:delete', ['node'], ['yes' => true, 'bundle' => 'article']);
        $this->drush('pm:uninstall', ['workspaces', 'node'], ['yes' => true]);
        parent::tearDown();
    }
}
