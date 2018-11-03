<?php

namespace Drupal\Tests\Core\Field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Provides setup method for testing base field definitions.
 */
abstract class BaseFieldDefinitionTestBase extends UnitTestCase {

  /**
   * The field definition used in this test.
   *
   * @var \Drupal\Core\Field\BaseFieldDefinition
   */
  protected $definition;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // getModuleAndPath() returns an array of the module name and directory.
    list($module_name, $module_dir) = $this->getModuleAndPath();

    $namespaces = new \ArrayObject();
    $namespaces["Drupal\\$module_name"] = $module_dir . '/src';

    $module_handler = $this->getMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $module_handler->expects($this->once())
      ->method('moduleExists')
      ->with($module_name)
      ->will($this->returnValue(TRUE));
    $typed_data_manager = $this->getMock(TypedDataManagerInterface::class);
    $plugin_manager = new FieldTypePluginManager(
      $namespaces,
      $this->getMock('Drupal\Core\Cache\CacheBackendInterface'),
      $module_handler,
      $typed_data_manager
    );

    $container = new ContainerBuilder();
    $container->set('plugin.manager.field.field_type', $plugin_manager);
    // The 'string_translation' service is used by the @Translation annotation.
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->definition = BaseFieldDefinition::create($this->getPluginId());
  }

  /**
   * Returns the plugin ID of the tested field type.
   *
   * @return string
   *   The plugin ID.
   */
  abstract protected function getPluginId();

  /**
   * Returns the module name and the module directory for the plugin.
   *
   * Function drupal_get_path() cannot be used here, because it is not available
   * in Drupal PHPUnit tests.
   *
   * @return array
   *   A one-dimensional array containing the following strings:
   *   - The module name.
   *   - The module directory, e.g. DRUPAL_CORE . 'core/modules/path'.
   */
  abstract protected function getModuleAndPath();

}
