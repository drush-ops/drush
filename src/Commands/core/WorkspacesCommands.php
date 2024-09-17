<?php

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\workspaces\WorkspaceOperationFactory;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class WorkspacesCommands extends DrushCommands {

  /**
   * Constructs a WorkspacesCommands object.
   */
  public function __construct(
    private readonly WorkspaceOperationFactory $factory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.operation_factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Publish a workspace.
   */
  #[CLI\Command(name: 'workspaces:publish')]
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
