<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Core\Url;

/**
 * Provides convenience methods for assertions in browser tests.
 */
trait DevelWebAssertHelper {

  /**
   * Asserts local tasks in the page output.
   *
   * @param array $routes
   *   A list of expected local tasks, prepared as an array of route names and
   *   their associated route parameters, to assert on the page (in the given
   *   order).
   * @param int $level
   *   (optional) The local tasks level to assert; 0 for primary, 1 for
   *   secondary. Defaults to 0.
   */
  protected function assertLocalTasks(array $routes, $level = 0) {
    $type_class = $level == 0 ? 'tabs primary' : 'tabs secondary';
    $elements = $this->xpath('//*[contains(@class, :class)]//a', [':class' => $type_class]);
    $this->assertTrue(count($elements), 'Local tasks found.');
    foreach ($routes as $index => $route_info) {
      list($route_name, $route_parameters) = $route_info;
      $expected = Url::fromRoute($route_name, $route_parameters)->toString();
      $this->assertEquals($expected, $elements[$index]->getAttribute('href'));
    }
  }

}
