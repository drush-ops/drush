<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

class EntityCommands extends DrushCommands
{
    protected $entityTypeManager;

    /**
     * EntityCommands constructor.
     */
    public function __construct(EntityTypeManagerInterface $entityTypeManager)
    {
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * Delete content entities.
     *
     * To delete configuration entities, see config:delete command.
     *
     * @param string $entity_type An entity machine name.
     * @param string $ids A comma delimited list of Ids.
     * @param array $options
     *
     * @option bundle Restrict deletion to the specified bundle. Ignored when ids is specified.
     * @option exclude Exclude certain entities from deletion. Ignored when ids is specified.
     * @option chunks Define how many entities will be deleted in the same step.
     * @usage drush entity:delete node --bundle=article
     *   Delete all article entities.
     * @usage drush entity:delete shortcut
     *   Delete all shortcut entities.
     * @usage drush entity:delete node 22,24
     *   Delete nodes 22 and 24.
     * @usage drush entity:delete node --exclude=9,14,81
     *   Delete all nodes except node 9, 14 and 81.
     * @usage drush entity:delete user
     *   Delete all users except uid=1.
     * @usage drush entity:delete node --chunks=5
     *   Delete all node entities in steps of 5.
     *
     * @command entity:delete
     * @aliases edel,entity-delete
     * @throws \Exception
     */
    public function delete(string $entity_type, $ids = null, array $options = ['bundle' => self::REQ, 'exclude' => self::REQ, 'chunks' => 50]): void
    {
        $query = $this->getQuery($entity_type, $ids, $options);
        $result = $query->execute();

        // Don't delete uid=1, uid=0.
        if ($entity_type == 'user') {
            unset($result[0], $result[1]);
        }

        if (empty($result)) {
            $this->logger()->success(dt('No matching entities found.'));
        } else {
            $this->io()->progressStart(count($result));
            foreach (array_chunk($result, $options['chunks'], true) as $chunk) {
                drush_op([$this, 'doDelete'], $entity_type, $chunk);
                $this->io()->progressAdvance(count($chunk));
            }
            $this->io()->progressFinish();
            $this->logger()->success(dt("Deleted !type entity Ids: !ids", ['!type' => $entity_type, '!ids' => implode(', ', array_values($result))]));
        }
    }

    /**
     * Actual delete method.
     *
     * @param string $entity_type
     * @param array $ids
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
     * Load and save entities.
     *
     * @param string $entity_type An entity machine name.
     * @param string $ids A comma delimited list of Ids.
     * @param array $options
     *
     * @option bundle Restrict to the specified bundle. Ignored when ids is specified.
     * @option exclude Exclude certain entities. Ignored when ids is specified.
     * @option chunks Define how many entities will be loaded in the same step.
     * @usage drush entity:save node --bundle=article
     *   Re-save all article entities.
     * @usage drush entity:save shortcut
     *   Re-save all shortcut entities.
     * @usage drush entity:save node 22,24
     *   Re-save nodes 22 and 24.
     * @usage drush entity:save node --exclude=9,14,81
     *   Re-save all nodes except node 9, 14 and 81.
     * @usage drush entity:save user
     *   Re-save all users.
     * @usage drush entity:save node --chunks=5
     *   Re-save all node entities in steps of 5.
     * @version 11.0
     *
     * @command entity:save
     * @aliases esav,entity-save
     * @throws \Exception
     */
    public function loadSave(string $entity_type, $ids = null, array $options = ['bundle' => self::REQ, 'exclude' => self::REQ, 'chunks' => 50]): void
    {
        $query = $this->getQuery($entity_type, $ids, $options);
        $result = $query->execute();

        if (empty($result)) {
            $this->logger()->success(dt('No matching entities found.'));
        } else {
            $this->io()->progressStart(count($result));
            foreach (array_chunk($result, $options['chunks'], true) as $chunk) {
                drush_op([$this, 'doSave'], $entity_type, $chunk);
                $this->io()->progressAdvance(count($chunk));
            }
            $this->io()->progressFinish();
            $this->logger()->success(dt("Saved !type entity ids: !ids", ['!type' => $entity_type, '!ids' => implode(', ', array_values($result))]));
        }
    }

    /**
     * Actual save method.
     *
     * @param string $entity_type
     * @param array $ids
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function doSave(string $entity_type, array $ids): void
    {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $entities = $storage->loadMultiple($ids);
        foreach ($entities as $entity) {
            $entity->save();
        }
    }

    /**
     * @param string $entity_type
     * @param string|null $ids
     * @param array $options
     * @return QueryInterface
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
        } elseif ($options['bundle'] || $options['exclude']) {
            if ($exclude = StringUtils::csvToArray((string) $options['exclude'])) {
                $idKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('id');
                $query = $query->condition($idKey, $exclude, 'NOT IN');
            }
            if ($bundle = $options['bundle']) {
                $bundleKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
                $query = $query->condition($bundleKey, $bundle);
            }
        }
        return $query;
    }
}
