<?php

namespace Drupal\Tests\views\Functional;

use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests the template retrieval of views.
 *
 * @group views
 * @see \Drupal\views_test_data\Plugin\views\style\StyleTemplateTest
 */
class ViewsTemplateTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view_display_template'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp(FALSE);

    $this->enableViewsTestModule();
    ViewTestData::createTestViews(get_class($this), ['views_test_config']);
  }

  /**
   * Tests render functionality.
   */
  public function testTemplate() {

    // Make sure that the rendering just calls the preprocess function once.
    $view = Views::getView('test_view_display_template');
    $output = $view->preview();

    // Check if we got the rendered output of our template file.
    $this->assertTrue(strpos(\Drupal::service('renderer')->renderRoot($output), 'This module defines its own display template.') !== FALSE, 'Display plugin DisplayTemplateTest defines its own template.');

  }

}
