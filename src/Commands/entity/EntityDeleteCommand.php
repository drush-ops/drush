<?php

declare(strict_types=1);

namespace Drush\Commands\entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Delete content entities.',
    aliases: ['edel', 'entity-delete'],
    usages: [
        'entity:delete node --bundle=article',
        'entity:delete shortcut',
        'entity:delete node 22,24',
        'entity:delete user',
        'entity:delete node --exclude=9,14,81',
        'entity:delete node --chunks=5',
        'entity:delete node --limit=500',
    ],
)]
final class EntityDeleteCommand
{
    use AutowireTrait;
    use EntityTrait;

    public const string NAME = 'entity:delete';

    public function __construct(
        protected readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('An entity machine name.', suggestedValues: [self::class, 'suggestEntityTypes'])]
        string $entityType,
        #[Argument('A comma delimited list of Ids.')]
        ?string $ids = null,
        #[Option('Restrict deletion to the specified bundle. Ignored when ids is specified.')]
        ?string $bundle = null,
        #[Option('Exclude certain entities from deletion. Ignored when ids is specified.')]
        ?string $exclude = null,
        #[Option('Specify how many entities will be deleted in the same step.')]
        int $chunks = 50,
        #[Option('Limit on the number of entities to delete.')]
        ?int $limit = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $this->validateEntityType($entityType);

        $result = $this->getQuery($entityType, $ids, $bundle, $exclude, $limit)->execute();

        // Don't delete uid=1, uid=0.
        if ($entityType === 'user') {
            unset($result[0], $result[1]);
        }

        if (empty($result)) {
            $io->success((string) $this->t('No matching entities found.'));
            return Command::SUCCESS;
        }

        if (empty($limit) && empty($ids)) {
            $question = $this->formatPlural(count($result), 'You are about to delete 1 entity. Do you wish to continue?', 'You are about to delete @count entities. Do you wish to continue?');
            // Drush's --yes makes the input non-interactive, so the default
            // answer is what -y produces.
            if (!$io->confirm((string) $question)) {
                throw new UserAbortException();
            }
        }

        $io->progressStart(count($result));
        foreach (array_chunk($result, max(1, $chunks), true) as $chunk) {
            $this->doDelete($entityType, $chunk);
            $io->progressAdvance(count($chunk));
        }
        $io->progressFinish();
        $io->success((string) $this->t('Deleted @type entity Ids: @ids', [
            '@type' => $entityType,
            '@ids' => implode(', ', array_values($result)),
        ]));

        return Command::SUCCESS;
    }

    /**
     * Actual delete method.
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function doDelete(string $entity_type, array $ids): void
    {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $entities = $storage->loadMultiple($ids);
        $storage->delete($entities);
    }
}
