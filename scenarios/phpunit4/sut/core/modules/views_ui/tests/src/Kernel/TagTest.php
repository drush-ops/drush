<?php

namespace Drupal\Tests\views_ui\Kernel;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views_ui\Controller\ViewsUIController;
use Drupal\Component\Utility\Html;
use Drupal\views\Entity\View;

/**
 * Tests the views ui tagging functionality.
 *
 * @group views_ui
 */
class TagTest extends ViewsKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['views', 'views_ui', 'user'];

  /**
   * Tests the views_ui_autocomplete_tag function.
   */
  public function testViewsUiAutocompleteTag() {
    \Drupal::moduleHandler()->loadInclude('views_ui', 'inc', 'admin');

    // Save 15 views with a tag.
    $tags = [];
    for ($i = 0; $i < 16; $i++) {
      $suffix = $i % 2 ? 'odd' : 'even';
      $tag = 'autocomplete_tag_test_' . $suffix . $this->randomMachineName();
      $tags[] = $tag;
      View::create(['tag' => $tag, 'id' => $this->randomMachineName()])->save();
    }

    // Make sure just ten results are returned.
    $controller = ViewsUIController::create($this->container);
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->query->set('q', 'autocomplete_tag_test');
    $result = $controller->autocompleteTag($request);
    $matches = (array) json_decode($result->getContent(), TRUE);
    $this->assertEqual(count($matches), 10, 'Make sure the maximum amount of tag results is 10.');

    // Make sure the returned array has the proper format.
    $suggestions = array_map(function ($tag) {
      return ['value' => $tag, 'label' => Html::escape($tag)];
    }, $tags);
    foreach ($matches as $match) {
      $this->assertTrue(in_array($match, $suggestions), 'Make sure the returned array has the proper format.');
    }

    // Make sure that matching by a certain prefix works.
    $request->query->set('q', 'autocomplete_tag_test_even');
    $result = $controller->autocompleteTag($request);
    $matches = (array) json_decode($result->getContent(), TRUE);
    $this->assertEqual(count($matches), 8, 'Make sure that only a subset is returned.');
    foreach ($matches as $tag) {
      $this->assertTrue(array_search($tag['value'], $tags) !== FALSE, format_string('Make sure the returned tag @tag actually exists.', ['@tag' => $tag['value']]));
    }

    // Make sure an invalid result doesn't return anything.
    $request->query->set('q', $this->randomMachineName());
    $result = $controller->autocompleteTag($request);
    $matches = (array) json_decode($result->getContent());
    $this->assertEqual(count($matches), 0, "Make sure an invalid tag doesn't return anything.");
  }

}
