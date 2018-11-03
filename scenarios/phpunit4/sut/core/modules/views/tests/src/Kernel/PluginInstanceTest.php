<?php

namespace Drupal\Tests\views\Kernel;

use Drupal\views\Views;

/**
 * Tests that an instance of all views plugins can be created.
 *
 * @group views
 */
class PluginInstanceTest extends ViewsKernelTestBase {

  /**
   * All views plugin types.
   *
   * @var array
   */
  protected $pluginTypes = [
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

  /**
   * An array of plugin definitions, keyed by plugin type.
   *
   * @var array
   */
  protected $definitions;

  protected function setUp($import_test_views = TRUE) {
    parent::setUp();

    $this->definitions = Views::getPluginDefinitions();
  }

  /**
   * Confirms that there is plugin data for all views plugin types.
   */
  public function testPluginData() {
    // Check that we have an array of data.
    $this->assertTrue(is_array($this->definitions), 'Plugin data is an array.');

    // Check all plugin types.
    foreach ($this->pluginTypes as $type) {
      $this->assertTrue(array_key_exists($type, $this->definitions), format_string('Key for plugin type @type found.', ['@type' => $type]));
      $this->assertTrue(is_array($this->definitions[$type]) && !empty($this->definitions[$type]), format_string('Plugin type @type has an array of plugins.', ['@type' => $type]));
    }

    // Tests that the plugin list has not missed any types.
    $diff = array_diff(array_keys($this->definitions), $this->pluginTypes);
    $this->assertTrue(empty($diff), 'All plugins were found and matched.');
  }

  /**
   * Tests creating instances of every views plugin.
   *
   * This will iterate through all plugins from _views_fetch_plugin_data().
   */
  public function testPluginInstances() {
    foreach ($this->definitions as $type => $plugins) {
      // Get a plugin manager for this type.
      $manager = $this->container->get("plugin.manager.views.$type");
      foreach ($plugins as $id => $definition) {
        // Get a reflection class for this plugin.
        // We only want to test true plugins, i.e. They extend PluginBase.
        $reflection = new \ReflectionClass($definition['class']);
        if ($reflection->isSubclassOf('Drupal\views\Plugin\views\PluginBase')) {
          // Create a plugin instance and check what it is. This is not just
          // good to check they can be created but for throwing any notices for
          // method signatures etc. too.
          $instance = $manager->createInstance($id);
          $this->assertTrue($instance instanceof $definition['class'], format_string('Instance of @type:@id created', ['@type' => $type, '@id' => $id]));
        }
      }
    }
  }

}
