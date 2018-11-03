<?php

namespace Drupal\field_ui\Routing;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\EnhancerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Enhances Field UI routes by adding proper information about the bundle name.
 */
class FieldUiRouteEnhancer implements EnhancerInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a FieldUiRouteEnhancer object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    if (!$this->applies($defaults[RouteObjectInterface::ROUTE_OBJECT])) {
      return $defaults;
    }

    if (($bundle = $this->entityManager->getDefinition($defaults['entity_type_id'])->getBundleEntityType()) && isset($defaults[$bundle])) {
      // Field UI forms only need the actual name of the bundle they're dealing
      // with, not an upcasted entity object, so provide a simple way for them
      // to get it.
      $defaults['bundle'] = $defaults['_raw_variables']->get($bundle);
    }

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  protected function applies(Route $route) {
    return ($route->hasOption('_field_ui'));
  }

}
