<?php

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\WorkspaceOperationFactory;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

final class WorkspacesCommands extends DrushCommands
{
    use AutowireTrait;

    const PUBLISH = 'workspaces:publish';

    protected ?WorkspaceOperationFactory $workspacesOperationFactory = null;

    /**
     * Constructs a WorkspacesCommands object.
     */
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
        parent::__construct();

        $container = Drush::getContainer();
        if ($container->has('workspaces.operation_factory')) {
            $this->workspacesOperationFactory = $container->get('workspaces.operation_factory');
        }
    }

    /**
   * Publish a workspace.
   */
    #[CLI\Command(name: self::PUBLISH)]
    #[CLI\Argument(name: 'id', description: 'The workspace to publish.')]
    #[CLI\Usage(name: 'workspaces:publish stage', description: 'Publish the stage workspace')]
    #[CLI\ValidateModulesEnabled(modules: ['workspaces'])]
    public function publish($id)
    {
        /** @var \Drupal\workspaces\Entity\Workspace $workspace */
        $workspace = $this->entityTypeManager->getStorage('workspace')->load($id);
        if (!$workspace) {
            throw new \Exception(dt('Workspace @id not found.', ['@id' => $id]));
        }

        $workspace_publisher = $this->workspacesOperationFactory->getPublisher($workspace);

        $args = [
            '%source_label' => $workspace->label(),
            '%target_label' => $workspace_publisher->getTargetLabel(),
        ];

      // Does this workspace have any content to publish?
        $diff = $workspace_publisher->getDifferringRevisionIdsOnSource();
        if (empty($diff)) {
            $this->logger()->success(dt('There are no changes that can be published from %source_label to %target_label.', $args));
            return;
        }

        $workspace->publish();
        $this->logger()->success(dt('Workspace %source_label published.', $args));
    }
}
