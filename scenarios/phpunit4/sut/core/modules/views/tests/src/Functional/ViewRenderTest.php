<?php

namespace Drupal\Tests\views\Functional;

use Drupal\views\Views;

/**
 * Tests general rendering of a view.
 *
 * @group views
 */
class ViewRenderTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view_render'];

  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->enableViewsTestModule();
  }

  /**
   * Tests render functionality.
   */
  public function testRender() {
    \Drupal::state()->set('views_render.test', 0);

    // Make sure that the rendering just calls the preprocess function once.
    $view = Views::getView('test_view_render');
    $output = $view->preview();
    $this->container->get('renderer')->renderRoot($output);

    $this->assertEqual(\Drupal::state()->get('views_render.test'), 1);
  }

}
