<?php

namespace Drupal\Core\Entity\Query;

@trigger_error('The ' . __NAMESPACE__ . '\QueryFactory class is deprecated in Drupal 8.3.0, will be removed before Drupal 9.0.0. Use \Drupal\Core\Entity\EntityStorageInterface::getQuery() or \Drupal\Core\Entity\EntityStorageInterface::getAggregateQuery() instead. See https://www.drupal.org/node/2849874.', E_USER_DEPRECATED);

use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Factory class Creating entity query objects.
 *
 * Any implementation of this service must call getQuery()/getAggregateQuery()
 * of the corresponding entity storage.
 *
 * @deprecated in Drupal 8.3.0, will be removed before Drupal 9.0.0. Use
 *   \Drupal\Core\Entity\EntityStorageInterface::getQuery() or
 *   \Drupal\Core\Entity\EntityStorageInterface::getAggregateQuery() instead.
 *
 * @see https://www.drupal.org/node/2849874
 * @see \Drupal\Core\Entity\EntityStorageBase::getQuery()
 */
class QueryFactory implements ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * Stores the entity manager used by the query.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager used by the query.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * Returns a query object for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object that can query the given entity type.
   */
  public function get($entity_type_id, $conjunction = 'AND') {
    return $this->entityManager->getStorage($entity_type_id)->getQuery($conjunction);
  }

  /**
   * Returns an aggregated query object for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\Core\Entity\Query\QueryAggregateInterface
   *   The aggregated query object that can query the given entity type.
   */
  public function getAggregate($entity_type_id, $conjunction = 'AND') {
    return $this->entityManager->getStorage($entity_type_id)->getAggregateQuery($conjunction);
  }

}
