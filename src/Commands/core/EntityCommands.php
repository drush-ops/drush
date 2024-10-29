<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Session\AccountInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

final class EntityCommands extends DrushCommands implements StdinAwareInterface
{
    use AutowireTrait;
    use StdinAwareTrait;

    const DELETE = 'entity:delete';
    const SAVE = 'entity:save';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected TimeInterface $time,
        protected AccountInterface $currentUser
    ) {
        parent::__construct();
    }

    /**
     * Delete content entities.
     *
     * To delete configuration entities, see config:delete command.
     */
    #[CLI\Command(name: self::DELETE, aliases: ['edel', 'entity-delete'])]
    #[CLI\Argument(name: 'entity_type', description: 'An entity machine name.')]
    #[CLI\Argument(name: 'ids', description: 'A comma delimited list of Ids.')]
    #[CLI\Option(name: 'bundle', description: 'Restrict deletion to the specified bundle. Ignored when ids is specified.')]
    #[CLI\Option(name: 'exclude', description: 'Exclude certain entities from deletion. Ignored when ids is specified.')]
    #[CLI\Option(name: 'chunks', description: 'Specify how many entities will be deleted in the same step.')]
    #[CLI\Option(name: 'limit', description: 'Limit on the number of entities to delete.')]
    #[CLI\Usage(name: 'drush entity:delete node --bundle=article', description: 'Delete all article entities.')]
    #[CLI\Usage(name: 'drush entity:delete shortcut', description: 'Delete all shortcut entities.')]
    #[CLI\Usage(name: 'drush entity:delete node 22,24', description: 'Delete nodes 22 and 24.')]
    #[CLI\Usage(name: 'drush entity:delete user', description: 'Delete all users except uid=1.')]
    #[CLI\Usage(name: 'drush entity:delete node --exclude=9,14,81', description: 'Delete all nodes except node 9, 14 and 81.')]
    #[CLI\Usage(name: 'drush entity:delete node --chunks=5', description: 'Delete all node entities in groups of 5.')]
    #[CLI\Usage(name: 'drush entity:delete node --limit=500', description: 'Delete 500 node entities.')]
    public function delete(string $entity_type, $ids = null, array $options = ['bundle' => self::REQ, 'exclude' => self::REQ, 'chunks' => 50, 'limit' => null]): void
    {
        $query = $this->getQuery($entity_type, $ids, $options);
        $result = $query->execute();

        // Don't delete uid=1, uid=0.
        if ($entity_type === 'user') {
            unset($result[0], $result[1]);
        }

        if (empty($result)) {
            $this->logger()->success(dt('No matching entities found.'));
        } else {
            $chunks = array_chunk($result, (int)$options['chunks'], true);
            $progress = $this->io()->progress('Deleting entitites', count($chunks));
            $progress->start();
            foreach ($chunks as $chunk) {
                drush_op([$this, 'doDelete'], $entity_type, $chunk);
                $progress->advance();
            }
            $progress->finish();
            $this->logger()->success(dt("Deleted !type entity Ids: !ids", ['!type' => $entity_type, '!ids' => implode(', ', array_values($result))]));
        }
    }

    /**
     * Actual delete method.
     *
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

    /**
     * Re-save entities, and publish/unpublish is specified.
     *
     * If passing in a file with an ID in each line, append a comma to each row.
     */
    #[CLI\Command(name: self::SAVE, aliases: ['esav', 'entity-save'])]
    #[CLI\Argument(name: 'entity_type', description: 'An entity machine name.')]
    #[CLI\Argument(name: 'ids', description: 'A comma delimited list of Ids. The list is read from Stdin if a value of <info>-</info> is provided for this option.')]
    #[CLI\Option(name: 'bundle', description: 'Restrict to the specified bundle. Ignored when ids is specified.')]
    #[CLI\Option(name: 'exclude', description: 'Exclude certain entities. Ignored when ids is specified.')]
    #[CLI\Option(name: 'chunks', description: 'Define how many entities will be loaded in the same step.')]
    #[CLI\Option(name: 'publish', description: 'Publish entities as they are saved. ')]
    #[CLI\Option(name: 'unpublish', description: 'Unpublish entities as they are saved.')]
    #[CLI\Option(name: 'state', description: 'Transition entities to the specified Content Moderation state. Do not pass --publish or --unpublish since the transition state determines handles publishing.')]
    #[CLI\Usage(name: 'drush entity:save node --bundle=article', description: 'Re-save all article entities.')]
    #[CLI\Usage(name: 'drush entity:save shortcut --unpublish --state=draft', description: 'Unpublish and transition all shortcut entities.')]
    #[CLI\Usage(name: 'drush entity:save node 22,24', description: 'Re-save nodes 22 and 24.')]
    #[CLI\Usage(name: 'cat /path/to/ids.csv | drush entity:save node -', description: 'Re-save the nodes whose Ids are listed in ids.csv.')]
    #[CLI\Usage(name: 'drush entity:save node --exclude=9,14,81', description: 'Re-save all nodes except node 9, 14 and 81.')]
    #[CLI\Usage(name: 'drush entity:save user', description: 'Re-save all users.')]
    #[CLI\Usage(name: 'drush entity:save node --chunks=5', description: 'Re-save all node entities in steps of 5.')]
    #[CLI\Version(version: '11.0')]
    public function loadSave(string $entity_type, $ids = null, array $options = ['bundle' => self::REQ, 'exclude' => self::REQ, 'chunks' => 50, 'publish' => false, 'unpublish' => false, 'state' => self::REQ]): void
    {
        if ($options['publish'] && $options['unpublish']) {
            throw new \InvalidArgumentException(dt('You may not specify both --publish and --unpublish.'));
        }
        if ($options['state'] && $options['publish']) {
            throw new \InvalidArgumentException(dt('You may not specify both --state and --publish.'));
        }
        if ($options['state'] && $options['unpublish']) {
            throw new \InvalidArgumentException(dt('You may not specify both --state and --unpublish.'));
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
            $ids = $this->stdin()->contents();
        }
        $query = $this->getQuery($entity_type, $ids, $options);
        $result = $query->execute();

        if (empty($result)) {
            $this->logger()->success(dt('No matching entities found.'));
        } else {
            $chunks = array_chunk($result, (int) $options['chunks'], true);
            $progress = $this->io()->progress('Saving entities', count($chunks));
            $progress->start();
            foreach ($chunks as $chunk) {
                drush_op([$this, 'doSave'], $entity_type, $chunk, $action, $state);
                $progress->advance();
            }
            $progress->finish();
            $this->logger()->success(dt("Saved !type entity ids: !ids", ['!type' => $entity_type, '!ids' => implode(', ', array_values($result))]));
            if ($action) {
                $this->logger()->success(dt("Entities have been !actioned.", ['!action' => $action]));
            }
            if ($state) {
                $this->logger()->success(dt("Entities have been transitioned to !state.", ['!state' => $state]));
            }
        }
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
        $message = [];
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $entities = $storage->loadMultiple($ids);
        foreach ($entities as $entity) {
            if (is_a($entity, RevisionableInterface::class)) {
                /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
                $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
                $entity = $storage->createRevision($entity, true);
            }
            if ($state) {
                // AutowireTrait does not support optional params so can't use DI.
                $moderationInformation = \Drupal::service('content_moderation.moderation_information');
                if (!$moderationInformation->isModeratedEntity($entity)) {
                    throw new \InvalidArgumentException(dt('!bundle !id does not support content moderation.', ['!bundle' => $entity->bundle(), '!id' => $entity->id()]));
                }

                // This line satisfies the bully that is phpstan.
                assert($entity instanceof ContentEntityInterface);
                $entity->set('moderation_state', $state);
                $message = 'State transitioned to ' . $state;
            }
            if ($action) {
                if (!is_a($entity, EntityPublishedInterface::class)) {
                    throw new \InvalidArgumentException(dt('!bundle !id does not support publish/unpublish.', ['!bundle' => $entity->bundle(), '!id' => $entity->id()]));
                }
                if ($action === 'publish') {
                    $entity->setPublished();
                    $message = 'Published.';
                } elseif ($action === 'unpublish') {
                    $entity->setUnpublished();
                    $message = 'Unpublished.';
                }
            }
            if (is_a($entity, RevisionLogInterface::class)) {
                $entity->setRevisionLogMessage('Re-saved by Drush entity:save. ' . $message);
                $entity->setRevisionCreationTime($this->time->getRequestTime());
                $entity->setRevisionUserId($this->currentUser->id());
            }
            if (is_a($entity, EntityChangedInterface::class)) {
                $entity->setChangedTime($this->time->getRequestTime());
            }
            $entity->save();
        }
    }

    /**
     * @param string|null $ids
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
