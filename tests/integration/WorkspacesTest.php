<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\PhpCommands;

/**
 * Tests Workspaces commands
 *
 * @group commands
 */
class WorkspacesTest extends \Unish\UnishIntegrationTestCase
{
    private \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->drush('pm:install', ['workspaces', 'node']);

        // $this->workspaceManager = \Drupal::service('workspaces.manager');
    }

    public function testWorkspaces(): void
    {
        $this->drush(PhpCommands::SCRIPT, ['workspaces'], ['script-path' => __DIR__ . '/resources']);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        // Cleanup any created content.
        $this->drush('entity:delete', ['node']);

        // Uninstall test modules.
        // $this->drush('pm:uninstall', ['workspaces', 'node']);

        parent::tearDown();
    }
}
