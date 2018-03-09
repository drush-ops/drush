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
     * @usage drush entity:delete node --bundle=article
     *   Delete all article entities.
     * @usage drush entity:delete shortcut
     *   Delete all shortcut entities.
     * @usage drush entity:delete node 22,24
     *   Delete nodes 22 and 24.
     *
     * @command entity:delete
     * @aliases edel,entity-delete
     * @throws \Exception
     */
    public function delete($entity_type, $ids = null, $options = ['bundle' => self::REQ])
    {
        $storage = $this->entityTypeManager->getStorage($entity_type);
        if ($ids = StringUtils::csvToArray($ids)) {
            $entities = $storage->loadMultiple($ids);
        } elseif ($bundle = $options['bundle']) {
            $bundleKey = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
            $entities = $storage->loadByProperties([$bundleKey => $bundle]);
        } else {
            $entities = $storage->loadMultiple();
        }
        if (empty($entities)) {
            throw new \Exception(dt('No matching entities found.'));
        }
        $storage->delete($entities);
        $this->logger()->success(dt('Deleted !type entity Ids: !ids', ['!type' => $entity_type, '!ids' => implode(', ', array_keys($entities))]));
    }
}
