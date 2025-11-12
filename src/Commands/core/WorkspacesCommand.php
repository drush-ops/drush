<?php

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\WorkspaceOperationFactory;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Publish a workspace.'
)]
#[CLI\ValidateModulesEnabled(modules: ['workspaces'])]
final class WorkspacesCommand extends Command
{
    use AutowireTrait;

    const NAME = 'workspaces:publish';

    protected ?WorkspaceOperationFactory $workspacesOperationFactory = null;

    /**
     * Constructs a WorkspacesCommands object.
     */
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        protected readonly ContainerInterface $container,
    ) {
        parent::__construct();

        if ($container->has('workspaces.operation_factory')) {
            $this->workspacesOperationFactory = $container->get('workspaces.operation_factory');
        }
    }

    protected function configure()
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'The workspace to publish.')
            ->addUsage('workspaces:publish stage');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $id = $input->getArgument('id');
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
            $io->success(dt('There are no changes that can be published from %source_label to %target_label.', $args));
            return self::SUCCESS;
        }

        $workspace->publish();
        $io->success(dt('Workspace %source_label published.', $args));
    }
}
