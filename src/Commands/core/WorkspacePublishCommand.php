<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceOperationFactory;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Publish a workspace.',
    usages: [
        'workspace:publish stage',
    ],
)]
final class WorkspacePublishCommand
{
    use AutowireTrait;
    use StringTranslationTrait;

    public const string NAME = 'workspace:publish';

    /**
     * The operation factory is null when the workspaces module is not
     * installed; AutowireTrait::create() falls back to the default when the
     * container has no such service.
     *
     * @todo Make the factory required and drop the moduleExists() checks once
     *   the `drush` entrypoint is retired. DoctorServiceProvider only registers
     *   this command with `dr` when workspaces is installed.
     */
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly ModuleHandlerInterface $moduleHandler,
        #[Autowire(service: 'workspaces.operation_factory')]
        private readonly ?WorkspaceOperationFactory $operationFactory = null,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The workspace to publish.', suggestedValues: [self::class, 'suggestWorkspaceIds'])]
        string $id,
    ): int {
        if (!$this->moduleHandler->moduleExists('workspaces') || $this->operationFactory === null) {
            throw new \InvalidArgumentException((string) $this->t('The workspaces module is not installed.'));
        }
        $workspace = $this->entityTypeManager->getStorage('workspace')->load($id);
        if (!$workspace instanceof WorkspaceInterface) {
            throw new \InvalidArgumentException((string) $this->t('Workspace @id not found.', ['@id' => $id]));
        }

        $io = new SymfonyStyle($input, $output);
        $publisher = $this->operationFactory->getPublisher($workspace);
        if (empty($publisher->getDifferringRevisionIdsOnSource())) {
            $io->success((string) $this->t('There are no changes that can be published from @source to @target.', [
                '@source' => $workspace->label(),
                '@target' => $publisher->getTargetLabel(),
            ]));
            return Command::SUCCESS;
        }

        $workspace->publish();
        $io->success((string) $this->t('Workspace @label published.', ['@label' => $workspace->label()]));
        return Command::SUCCESS;
    }

    /**
     * Shell completion for the workspace id argument.
     */
    public function suggestWorkspaceIds(CompletionInput $input): array
    {
        if (!$this->moduleHandler->moduleExists('workspaces')) {
            return [];
        }
        return array_keys($this->entityTypeManager->getStorage('workspace')->loadMultiple());
    }
}
