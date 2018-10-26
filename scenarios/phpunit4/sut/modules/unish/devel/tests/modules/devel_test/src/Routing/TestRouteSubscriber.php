<?php

namespace Drupal\devel_test\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Router subscriber class for testing purpose.
 */
class TestRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    \Drupal::state()->set('devel_test_route_rebuild','Router rebuild fired');
  }

}
