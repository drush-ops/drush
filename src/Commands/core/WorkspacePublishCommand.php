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
#[CLI\Version(version: '13.7')]
final class WorkspacePublishCommand extends Command
{
    use AutowireTrait;

    const NAME = 'workspace:publish';

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
            ->addUsage('workspace:publish stage');
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

        // Does this workspace have any content to publish?
        $diff = $workspace_publisher->getDifferringRevisionIdsOnSource();
        if (empty($diff)) {
            $io->success(sprintf('There are no changes that can be published from %s to %s', $workspace->label(), $workspace_publisher->getTargetLabel()));
            return self::SUCCESS;
        }

        $workspace->publish();
        $io->success(sprintf('Workspace %s published.', $workspace->label()));
        return self::SUCCESS;
    }
}
