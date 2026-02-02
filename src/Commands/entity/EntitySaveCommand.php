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
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Re-save entities, and publish/unpublish if specified.',
    aliases: ['esav', 'entity-save'],
)]
#[CLI\Version(version: '11.0')]
final class EntitySaveCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'entity:save';

    public function __construct(
        protected readonly EntityTypeManagerInterface $entityTypeManager,
        protected readonly TimeInterface $time,
        protected readonly AccountInterface $currentUser,
        protected readonly ?ModerationInformationInterface $moderationInformation = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity_type', InputArgument::REQUIRED, 'An entity machine name.')
            ->addArgument('ids', InputArgument::OPTIONAL, 'A comma delimited list of Ids. The list is read from Stdin if a value of <info>-</info> is provided for this option.')
            ->addOption('bundle', null, InputOption::VALUE_REQUIRED, 'Restrict to the specified bundle. Ignored when ids is specified.')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Exclude certain entities. Ignored when ids is specified.')
            ->addOption('chunks', null, InputOption::VALUE_REQUIRED, 'Define how many entities will be loaded in the same step.', '50')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit on the number of entities to save.')
            ->addOption('publish', null, InputOption::VALUE_NONE, 'Publish entities as they are saved.')
            ->addOption('unpublish', null, InputOption::VALUE_NONE, 'Unpublish entities as they are saved.')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Transition entities to the specified Content Moderation state. Do not pass --publish or --unpublish since the transition state determines handles publishing.')
            ->addUsage('entity:save node --bundle=article')
            ->addUsage('entity:save shortcut --unpublish --state=draft')
            ->addUsage('entity:save node 22,24')
            ->addUsage('cat /path/to/ids.csv | drush entity:save node -')
            ->addUsage('entity:save node --exclude=9,14,81')
            ->addUsage('entity:save user')
            ->addUsage('entity:save node --chunks=5')
            ->setHelp('If passing in a file with an ID in each line, append a comma to each row.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $entity_type = $input->getArgument('entity_type');
        $ids = $input->getArgument('ids');
        $options = $input->getOptions();

        if ($options['publish'] && $options['unpublish']) {
            throw new \InvalidArgumentException('You may not specify both --publish and --unpublish.');
        }
        if ($options['state'] && $options['publish']) {
            throw new \InvalidArgumentException('You may not specify both --state and --publish.');
        }
        if ($options['state'] && $options['unpublish']) {
            throw new \InvalidArgumentException('You may not specify both --state and --unpublish.');
        }

        $action = $state = null;
        if ($options['state']) {
            $state = $options['state'];
        } elseif ($options['publish']) {
            $action = 'publish';
        } elseif ($options['unpublish']) {
            $action = 'unpublish';
        }

        if ($ids === '-') {
            $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : STDIN;
            $ids = stream_get_contents($inputStream);
        }
        $query = $this->getQuery($entity_type, $ids, $options);
        $result = $query->execute();

        if (empty($result)) {
            $io->success('No matching entities found.');
        } else {
            $chunks = array_chunk($result, (int) $options['chunks'], true);
            $io->progressStart(count($result));
            foreach ($chunks as $chunk) {
                drush_op($this->doSave(...), $entity_type, $chunk, $action, $state);
                $io->progressAdvance(count($chunk));
            }
            $io->progressFinish();
            $io->success("Saved {type} entity ids: {ids}", ['type' => $entity_type, 'ids' => implode(', ', array_values($result))]);
            if ($action) {
                $io->success("Entities have been {actioned}.", ['!actioned' => $action]);
            }
            if ($state) {
                $io->success("Entities have been transitioned to {state}.", ['state' => $state]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Actual save method.
     *
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
                $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
                $entity = $storage->createRevision($entity, true);
            }
            if ($state) {
                assert($this->moderationInformation instanceof ModerationInformationInterface);
                if (!$this->moderationInformation->isModeratedEntity($entity)) {
                    throw new \InvalidArgumentException(sprintf('%s %s does not support content moderation.', $entity->bundle(), $entity->id()));
                }

                // This line satisfies the bully that is phpstan.
                assert($entity instanceof ContentEntityInterface);
                $entity->set('moderation_state', $state);
                $message = 'State transitioned to ' . $state;
            }
            if ($action) {
                if (!$entity instanceof EntityPublishedInterface) {
                    throw new \InvalidArgumentException(sprintf('%s %s does not support publish/unpublish.', $entity->bundle(), $entity->id()));
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

    /**
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     */
    protected function getQuery(string $entity_type, ?string $ids, array $options): QueryInterface
    {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $query = $storage->getQuery()->accessCheck(false);
        if ($ids = StringUtils::csvToArray((string) $ids)) {
            $idKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('id');
            $query = $query->condition($idKey, $ids, 'IN');
        } elseif ($options['bundle'] || $options['exclude'] || $options['limit']) {
            if ($exclude = StringUtils::csvToArray((string) $options['exclude'])) {
                $idKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('id');
                $query = $query->condition($idKey, $exclude, 'NOT IN');
            }
            if ($bundle = $options['bundle']) {
                $bundleKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
                $query = $query->condition($bundleKey, $bundle);
            }
            if ($limit = $options['limit']) {
                $query->range(0, $limit);
            }
        }
        return $query;
    }
}
