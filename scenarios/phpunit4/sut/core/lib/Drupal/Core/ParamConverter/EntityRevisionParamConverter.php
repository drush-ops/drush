<?php

namespace Drupal\Core\ParamConverter;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting entity revision IDs to full objects.
 *
 * This is useful for pages which want to show a specific revision, like
 * "/entity_example/{entity_example}/revision/{entity_example_revision}".
 *
 *
 * In order to use it you should specify some additional options in your route:
 * @code
 * example.route:
 *   path: /foo/{entity_example_revision}
 *   options:
 *     parameters:
 *       entity_example_revision:
 *         type: entity_revision:entity_example
 * @endcode
 */
class EntityRevisionParamConverter implements ParamConverterInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Creates a new EntityRevisionParamConverter instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    list (, $entity_type_id) = explode(':', $definition['type'], 2);
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->loadRevision($value);
    return $this->entityRepository->getTranslationFromContext($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return isset($definition['type']) && strpos($definition['type'], 'entity_revision:') !== FALSE;
  }

}
