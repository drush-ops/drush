<?php

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\views\Views;

/**
 * Tests unformatted style functionality.
 *
 * @group views
 */
class StyleUnformattedTest extends StyleTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Make sure that the default css classes works as expected.
   */
  public function testDefaultRowClasses() {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $output = $view->preview();
    $this->storeViewPreview(\Drupal::service('renderer')->renderRoot($output));

    $rows = $this->elements->body->div->div;
    $count = 0;
    $count_result = count($view->result);
    foreach ($rows as $row) {
      $count++;
      $attributes = $row->attributes();
      $class = (string) $attributes['class'][0];
      $this->assertTrue(strpos($class, 'views-row') !== FALSE, 'Make sure that the views row class is set right.');
    }
    $this->assertIdentical($count, $count_result);
  }

}
