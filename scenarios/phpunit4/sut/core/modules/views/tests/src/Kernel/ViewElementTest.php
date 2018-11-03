<?php

namespace Drupal\Tests\views\Kernel;

use Drupal\views\Views;

/**
 * Tests the view render element.
 *
 * @group views
 */
class ViewElementTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view_embed'];

  /**
   * Tests the rendered output and form output of a view element.
   */
  public function testViewElement() {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $view = Views::getView('test_view_embed');

    // Get the render array, #embed must be FALSE since this is the default
    // display.
    $render = $view->buildRenderable();
    $this->assertEqual($render['#embed'], FALSE);
    $this->setRawContent($renderer->renderRoot($render));

    $xpath = $this->xpath('//div[@class="views-element-container"]');
    $this->assertTrue($xpath, 'The view container has been found in the rendered output.');

    // There should be 5 rows in the results.
    $xpath = $this->xpath('//div[@class="views-row"]');
    $this->assertEqual(count($xpath), 5);

    // Add an argument and save the view.
    $view->displayHandlers->get('default')->overrideOption('arguments', [
      'age' => [
        'default_action' => 'ignore',
        'title' => '',
        'default_argument_type' => 'fixed',
        'validate' => [
          'type' => 'none',
          'fail' => 'not found',
        ],
        'break_phrase' => FALSE,
        'not' => FALSE,
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'plugin_id' => 'numeric',
      ],
    ]);
    $view->save();

    // Test the render array again.
    $view = Views::getView('test_view_embed');
    $render = $view->buildRenderable(NULL, [25]);
    $this->setRawContent($renderer->renderRoot($render));
    // There should be 1 row in the results, 'John' arg 25.
    $xpath = $this->xpath('//div[@class="views-row"]');
    $this->assertEqual(count($xpath), 1);
  }

  /**
   * Tests the rendered output and form output of a view element, using the
   * embed display plugin.
   */
  public function testViewElementEmbed() {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $view = Views::getView('test_view_embed');

    // Get the render array, #embed must be TRUE since this is an embed display.
    $render = $view->buildRenderable('embed_1');
    $this->assertEqual($render['#embed'], TRUE);
    $this->setRawContent($renderer->renderRoot($render));

    $xpath = $this->xpath('//div[@class="views-element-container"]');
    $this->assertTrue($xpath, 'The view container has been found in the rendered output.');

    // There should be 5 rows in the results.
    $xpath = $this->xpath('//div[@class="views-row"]');
    $this->assertEqual(count($xpath), 5);

    // Add an argument and save the view.
    $view->displayHandlers->get('default')->overrideOption('arguments', [
      'age' => [
        'default_action' => 'ignore',
        'title' => '',
        'default_argument_type' => 'fixed',
        'validate' => [
          'type' => 'none',
          'fail' => 'not found',
        ],
        'break_phrase' => FALSE,
        'not' => FALSE,
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'plugin_id' => 'numeric',
      ],
    ]);
    $view->save();

    // Test the render array again.
    $view = Views::getView('test_view_embed');
    $render = $view->buildRenderable('embed_1', [25]);
    $this->setRawContent($renderer->renderRoot($render));
    // There should be 1 row in the results, 'John' arg 25.
    $xpath = $this->xpath('//div[@class="views-row"]');
    $this->assertEqual(count($xpath), 1);

    // Tests the render array with an exposed filter.
    $view = Views::getView('test_view_embed');
    $render = $view->buildRenderable('embed_2');
    $this->setRawContent($renderer->renderRoot($render));

    // Ensure that the exposed form is rendered.
    $this->assertEqual(1, count($this->xpath('//form[@class="views-exposed-form"]')));
  }

}
