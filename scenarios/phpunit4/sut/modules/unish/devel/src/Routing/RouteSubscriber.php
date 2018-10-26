<?php

namespace Drupal\devel\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Devel routes.
 *
 * @see \Drupal\devel\Controller\EntityDebugController
 * @see \Drupal\devel\Plugin\Derivative\DevelLocalTask
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route = $this->getEntityLoadRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.devel_load", $route);
      }
      if ($route = $this->getEntityRenderRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.devel_render", $route);
      }
      if ($route = $this->getEntityTypeDefinitionRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.devel_definition", $route);
      }
    }
  }

  /**
   * Gets the entity load route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityLoadRoute(EntityTypeInterface $entity_type) {
    if ($devel_load = $entity_type->getLinkTemplate('devel-load')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($devel_load);
      $route
        ->addDefaults([
          '_controller' => '\Drupal\devel\Controller\EntityDebugController::entityLoad',
          '_title' => 'Devel Load',
        ])
        ->addRequirements([
          '_permission' => 'access devel information',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_devel_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }
  }

  /**
   * Gets the entity render route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityRenderRoute(EntityTypeInterface $entity_type) {
    if ($devel_render = $entity_type->getLinkTemplate('devel-render')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($devel_render);
      $route
        ->addDefaults([
          '_controller' => '\Drupal\devel\Controller\EntityDebugController::entityRender',
          '_title' => 'Devel Render',
        ])
        ->addRequirements([
          '_permission' => 'access devel information'
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_devel_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }
  }

  /**
   * Gets the entity type definition route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEntityTypeDefinitionRoute(EntityTypeInterface $entity_type) {
    if ($devel_definition = $entity_type->getLinkTemplate('devel-definition')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($devel_definition);
      $route
        ->addDefaults([
          '_controller' => '\Drupal\devel\Controller\EntityDebugController::entityTypeDefinition',
          '_title' => 'Entity type definition',
        ])
        ->addRequirements([
          '_permission' => 'access devel information'
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_devel_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
