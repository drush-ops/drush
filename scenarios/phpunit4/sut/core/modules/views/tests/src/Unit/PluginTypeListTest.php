<?php

namespace Drupal\Tests\views\Unit;

use Drupal\views\ViewExecutable;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that list of plugin is correct.
 *
 * @group views
 */
class PluginTypeListTest extends UnitTestCase {

  /**
   * Tests the plugins list is correct.
   */
  public function testPluginList() {
    $plugin_list = [
      'access',
      'area',
      'argument',
      'argument_default',
      'argument_validator',
      'cache',
      'display_extender',
      'display',
      'exposed_form',
      'field',
      'filter',
      'join',
      'pager',
      'query',
      'relationship',
      'row',
      'sort',
      'style',
      'wizard',
    ];

    $diff = array_diff($plugin_list, ViewExecutable::getPluginTypes());
    $this->assertTrue(empty($diff), 'The plugin list is correct');
  }

}
