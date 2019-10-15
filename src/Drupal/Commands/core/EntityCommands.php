<?php

namespace Drush\Drupal\Commands\core;

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
     *
     * @command entity:delete
     * @aliases edel,entity-delete
     * @throws \Exception
     */
    public function delete($entity_type, $ids = null, $options = ['bundle' => self::REQ, 'exclude' => self::REQ])
    {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        if ($ids = StringUtils::csvToArray($ids)) {
            $entities = $storage->loadMultiple($ids);
        } elseif ($options['bundle'] || $options['exclude']) {
            $query = $storage->getQuery();
            if ($exclude = StringUtils::csvToArray($options['exclude'])) {
                $idKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('id');
                $query = $query->condition($idKey, $exclude, 'NOT IN');
            }
            if ($bundle = $options['bundle']) {
                $bundleKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
                $query = $query->condition($bundleKey, $bundle);
            }
            $result = $query->execute();
            $entities = $storage->loadMultiple($result);
        } else {
            $entities = $storage->loadMultiple();
        }

        // Don't delete uid=1, uid=0.
        if ($entity_type == 'user') {
            unset($entities[1]);
            unset($entities[0]);
        }

        if (empty($entities)) {
            $this->logger()->success(dt('No matching entities found.'));
        } else {
            $storage->delete($entities);
            $this->logger()->success(dt('Deleted !type entity Ids: !ids', ['!type' => $entity_type, '!ids' => implode(', ', array_keys($entities))]));
        }
    }
}
