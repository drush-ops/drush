<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class EntityCommands extends DrushCommands
{
    const DELETE = 'entity:delete';
    const SAVE = 'entity:save';

    public function __construct(protected EntityTypeManagerInterface $entityTypeManager)
    {
        parent::__construct();
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('entity_type.manager'),
        );

        return $commandHandler;
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
    #[CLI\Usage(name: 'drush entity:delete node --bundle=article', description: 'Delete all article entities.')]
    #[CLI\Usage(name: 'drush entity:delete shortcut', description: 'Delete all shortcut entities.')]
    #[CLI\Usage(name: 'drush entity:delete node 22,24', description: 'Delete nodes 22 and 24.')]
    #[CLI\Usage(name: 'drush entity:delete user', description: 'Delete all users except uid=1.')]
    #[CLI\Usage(name: 'drush entity:delete node --exclude=9,14,81', description: 'Delete all nodes except node 9, 14 and 81.')]
    #[CLI\Usage(name: 'drush entity:delete node --chunks=5', description: 'Delete all node entities in steps of 5.')]
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
            foreach (array_chunk($result, (int) $options['chunks'], true) as $chunk) {
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
     */
    #[CLI\Command(name: self::SAVE, aliases: ['esav', 'entity-save'])]
    #[CLI\Argument(name: 'entity_type', description: 'An entity machine name.')]
    #[CLI\Argument(name: 'ids', description: 'A comma delimited list of Ids.')]
    #[CLI\Option(name: 'bundle', description: 'Restrict to the specified bundle. Ignored when ids is specified.')]
    #[CLI\Option(name: 'exclude', description: 'Exclude certain entities. Ignored when ids is specified.')]
    #[CLI\Option(name: 'chunks', description: 'Define how many entities will be loaded in the same step.')]
    #[CLI\Usage(name: 'drush entity:save node --bundle=article', description: 'Re-save all article entities.')]
    #[CLI\Usage(name: 'drush entity:save shortcut', description: 'Re-save all shortcut entities.')]
    #[CLI\Usage(name: 'drush entity:save node 22,24', description: 'Re-save nodes 22 and 24.')]
    #[CLI\Usage(name: 'drush entity:save node --exclude=9,14,81', description: 'Re-save all nodes except node 9, 14 and 81.')]
    #[CLI\Usage(name: 'drush entity:save user', description: 'Re-save all users.')]
    #[CLI\Usage(name: 'drush entity:save node --chunks=5', description: 'Re-save all node entities in steps of 5.')]
    #[CLI\Version(version: '11.0')]
    public function loadSave(string $entity_type, $ids = null, array $options = ['bundle' => self::REQ, 'exclude' => self::REQ, 'chunks' => 50]): void
    {
        $query = $this->getQuery($entity_type, $ids, $options);
        $result = $query->execute();

        if (empty($result)) {
            $this->logger()->success(dt('No matching entities found.'));
        } else {
            $this->io()->progressStart(count($result));
            foreach (array_chunk($result, (int) $options['chunks'], true) as $chunk) {
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
