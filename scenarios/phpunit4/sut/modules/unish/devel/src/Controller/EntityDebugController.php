<?php

namespace Drupal\devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\devel\DevelDumperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for devel entity debug.
 *
 * @see \Drupal\devel\Routing\RouteSubscriber
 * @see \Drupal\devel\Plugin\Derivative\DevelLocalTask
 */
class EntityDebugController extends ControllerBase {

  /**
   * The dumper service.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $dumper;

  /**
   * EntityDebugController constructor.
   *
   * @param \Drupal\devel\DevelDumperManagerInterface $dumper
   *   The dumper service.
   */
  public function __construct(DevelDumperManagerInterface $dumper) {
    $this->dumper = $dumper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('devel.dumper'));
  }

  /**
   * Returns the entity type definition of the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function entityTypeDefinition(RouteMatchInterface $route_match) {
    $output = [];

    $entity = $this->getEntityFromRouteMatch($route_match);

    if ($entity instanceof EntityInterface) {
      $output = $this->dumper->exportAsRenderable($entity->getEntityType());
    }

    return $output;
  }

  /**
   * Returns the loaded structure of the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function entityLoad(RouteMatchInterface $route_match) {
    $output = [];

    $entity = $this->getEntityFromRouteMatch($route_match);

    if ($entity instanceof EntityInterface) {
      // Field definitions are lazy loaded and are populated only when needed.
      // By calling ::getFieldDefinitions() we are sure that field definitions
      // are populated and available in the dump output.
      // @see https://www.drupal.org/node/2311557
      if($entity instanceof FieldableEntityInterface) {
        $entity->getFieldDefinitions();
      }

      $output = $this->dumper->exportAsRenderable($entity);
    }

    return $output;
  }

  /**
   * Returns the render structure of the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function entityRender(RouteMatchInterface $route_match) {
    $output = [];

    $entity = $this->getEntityFromRouteMatch($route_match);

    if ($entity instanceof EntityInterface) {
      $entity_type_id = $entity->getEntityTypeId();
      $view_hook = $entity_type_id . '_view';

      $build = [];
      // If module implements own {entity_type}_view() hook use it, otherwise
      // fallback to the entity view builder if available.
      if (function_exists($view_hook)) {
        $build = $view_hook($entity);
      }
      elseif ($this->entityTypeManager()->hasHandler($entity_type_id, 'view_builder')) {
        $build = $this->entityTypeManager()->getViewBuilder($entity_type_id)->view($entity);
      }

      $output = $this->dumper->exportAsRenderable($build);
    }

    return $output;
  }

  /**
   * Retrieves entity from route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object as determined from the passed-in route match.
   */
  protected function getEntityFromRouteMatch(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()->getOption('_devel_entity_type_id');
    $entity = $route_match->getParameter($parameter_name);
    return $entity;
  }

}
