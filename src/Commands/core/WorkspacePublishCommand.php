<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceOperationFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    use StringTranslationTrait;

    public const string NAME = 'workspace:publish';

    /**
     * DoctorServiceProvider registers this command only when the workspaces
     * module is installed, so its services are always available.
     */
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly WorkspaceOperationFactory $operationFactory,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The workspace to publish.', suggestedValues: [self::class, 'suggestWorkspaceIds'])]
        string $id,
    ): int {
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
        return array_keys($this->entityTypeManager->getStorage('workspace')->loadMultiple());
    }
}
