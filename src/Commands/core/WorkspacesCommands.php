<?php

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\WorkspaceOperationFactory;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;

final class WorkspacesCommands extends DrushCommands {
    use AutowireTrait;

    const PUBLISH = 'workspaces:publish';

    private ?WorkspaceOperationFactory $workspacesOperationFactory;

    /**
   * Constructs a WorkspacesCommands object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
     parent::__construct();
     /**
     * Since we use Autowire and our service is in a non-required module, we
     *     - Get the container ourselves.
     *     - Our service variable can be null.
     */
     $container = Drush::getContainer();
     if ($container->has('plugin.manager.migration')) {
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
  public function commandName($id) {

    $workspace = $this->entityTypeManager->getStorage('workspace')->load($id);

    $workspace_publisher = $this->factory->getPublisher($workspace);

    $args = [
      '%source_label' => $workspace->label(),
      '%target_label' => $workspace_publisher->getTargetLabel(),
    ];

    // Does this workspace have any content to publish?
    $diff = $workspace_publisher->getDifferringRevisionIdsOnSource();
    if (empty($diff)) {
      $this->io()->warning(dt('There are no changes that can be published from %source_label to %target_label.', $args));
      return;
    }

    $workspace->publish();
    $this->logger()->success(dt('Workspace %source_label published.', $args));
  }

}
