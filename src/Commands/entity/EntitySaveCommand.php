<?php

declare(strict_types=1);

namespace Drush\Commands\entity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Re-save entities, and publish/unpublish if specified.',
    aliases: ['esav', 'entity-save'],
    usages: [
        'entity:save node --bundle=article',
        'entity:save shortcut --unpublish --state=draft',
        'entity:save node 22,24',
        'cat /path/to/ids.csv | drush entity:save node -',
        'entity:save node --exclude=9,14,81',
        'entity:save user',
        'entity:save node --chunks=5',
    ],
    help: 'If passing in a file with an ID in each line, append a comma to each row.',
)]
final class EntitySaveCommand
{
    use AutowireTrait;
    use EntityTrait;

    public const string NAME = 'entity:save';

    public function __construct(
        protected readonly EntityTypeManagerInterface $entityTypeManager,
        protected readonly TimeInterface $time,
        protected readonly AccountInterface $currentUser,
        protected readonly ?ModerationInformationInterface $moderationInformation = null
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('An entity machine name.', suggestedValues: [self::class, 'suggestEntityTypes'])]
        string $entityType,
        #[Argument('A comma delimited list of Ids. The list is read from Stdin if a value of <info>-</info> is provided for this option.')]
        ?string $ids = null,
        #[Option('Restrict to the specified bundle. Ignored when ids is specified.')]
        ?string $bundle = null,
        #[Option('Exclude certain entities. Ignored when ids is specified.')]
        ?string $exclude = null,
        #[Option('Define how many entities will be loaded in the same step.')]
        int $chunks = 50,
        #[Option('Limit on the number of entities to save.')]
        ?int $limit = null,
        #[Option('Publish entities as they are saved.')]
        bool $publish = false,
        #[Option('Unpublish entities as they are saved.')]
        bool $unpublish = false,
        #[Option('Transition entities to the specified Content Moderation state. Do not pass --publish or --unpublish since the transition state determines handles publishing.')]
        ?string $state = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $this->validateEntityType($entityType);

        if ($publish && $unpublish) {
            throw new \InvalidArgumentException((string) $this->t('You may not specify both --publish and --unpublish.'));
        }
        if ($state && $publish) {
            throw new \InvalidArgumentException((string) $this->t('You may not specify both --state and --publish.'));
        }
        if ($state && $unpublish) {
            throw new \InvalidArgumentException((string) $this->t('You may not specify both --state and --unpublish.'));
        }

        $action = null;
        if ($state) {
            // The moderation state determines publishing.
        } elseif ($publish) {
            $action = 'publish';
        } elseif ($unpublish) {
            $action = 'unpublish';
        }

        // Special flag indicating that the ids have been passed via stdin.
        if ($ids === '-') {
            // Under CommandTester this reads what setInputs() provided.
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $ids = stream_get_contents($stream ?? STDIN);
        }

        $result = $this->getQuery($entityType, $ids, $bundle, $exclude, $limit)->execute();

        if (empty($result)) {
            $io->success((string) $this->t('No matching entities found.'));
            return Command::SUCCESS;
        }

        $io->progressStart(count($result));
        foreach (array_chunk($result, max(1, $chunks), true) as $chunk) {
            $this->doSave($entityType, $chunk, $action, $state);
            $io->progressAdvance(count($chunk));
        }
        $io->progressFinish();
        $io->success((string) $this->t('Saved @type entity ids: @ids', [
            '@type' => $entityType,
            '@ids' => implode(', ', array_values($result)),
        ]));
        if ($action === 'publish') {
            $io->success((string) $this->t('Entities have been published.'));
        } elseif ($action === 'unpublish') {
            $io->success((string) $this->t('Entities have been unpublished.'));
        }
        if ($state) {
            $io->success((string) $this->t('Entities have been transitioned to @state.', ['@state' => $state]));
        }

        return Command::SUCCESS;
    }

    /**
     * Actual save method.
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function doSave(string $entity_type, array $ids, ?string $action, ?string $state): void
    {
        $message = '';
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $entities = $storage->loadMultiple($ids);
        $is_revisionable = $this->entityTypeManager->getDefinition($entity_type)->isRevisionable();
        foreach ($entities as $entity) {
            if ($is_revisionable) {
                /** @var ContentEntityStorageInterface $storage */
                $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
                $entity = $storage->createRevision($entity, true);
            }
            if ($state) {
                if (!$this->moderationInformation?->isModeratedEntity($entity)) {
                    throw new \InvalidArgumentException((string) $this->t('@bundle @id does not support content moderation.', [
                        '@bundle' => $entity->bundle(),
                        '@id' => $entity->id(),
                    ]));
                }

                // This line satisfies the bully that is phpstan.
                assert($entity instanceof ContentEntityInterface);
                $entity->set('moderation_state', $state);
                $message = 'State transitioned to ' . $state;
            }
            if ($action) {
                if (!$entity instanceof EntityPublishedInterface) {
                    throw new \InvalidArgumentException((string) $this->t('@bundle @id does not support publish/unpublish.', [
                        '@bundle' => $entity->bundle(),
                        '@id' => $entity->id(),
                    ]));
                }
                if ($action === 'publish') {
                    $entity->setPublished();
                    $message = 'Published.';
                } elseif ($action === 'unpublish') {
                    $entity->setUnpublished();
                    $message = 'Unpublished.';
                }
            }
            if ($is_revisionable) {
                // This line satisfies the bully that is phpstan.
                assert($entity instanceof RevisionLogInterface);
                $entity->setRevisionLogMessage('Re-saved by Drush entity:save. ' . $message);
                $entity->setRevisionCreationTime($this->time->getRequestTime());
                $entity->setRevisionUserId($this->currentUser->id());
            }
            if ($entity instanceof EntityChangedInterface) {
                $entity->setChangedTime($this->time->getRequestTime());
            }
            $entity->save();
        }
    }
}
