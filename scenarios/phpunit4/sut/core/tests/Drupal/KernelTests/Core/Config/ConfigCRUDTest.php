<?php

namespace Drupal\KernelTests\Core\Config;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigNameException;
use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\UnsupportedDataTypeConfigException;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests CRUD operations on configuration objects.
 *
 * @group config
 */
class ConfigCRUDTest extends KernelTestBase {

  /**
   * Exempt from strict schema checking.
   *
   * @see \Drupal\Core\Config\Development\ConfigSchemaChecker
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system'];

  /**
   * Tests CRUD operations.
   */
  public function testCRUD() {
    $storage = $this->container->get('config.storage');
    $config_factory = $this->container->get('config.factory');
    $name = 'config_test.crud';

    $config = $this->config($name);
    $this->assertIdentical($config->isNew(), TRUE);

    // Create a new configuration object.
    $config->set('value', 'initial');
    $config->save();
    $this->assertIdentical($config->isNew(), FALSE);

    // Verify the active configuration contains the saved value.
    $actual_data = $storage->read($name);
    $this->assertIdentical($actual_data, ['value' => 'initial']);

    // Update the configuration object instance.
    $config->set('value', 'instance-update');
    $config->save();
    $this->assertIdentical($config->isNew(), FALSE);

    // Verify the active configuration contains the updated value.
    $actual_data = $storage->read($name);
    $this->assertIdentical($actual_data, ['value' => 'instance-update']);

    // Verify a call to $this->config() immediately returns the updated value.
    $new_config = $this->config($name);
    $this->assertIdentical($new_config->get(), $config->get());
    $this->assertIdentical($config->isNew(), FALSE);

    // Pollute the config factory static cache.
    $config_factory->getEditable($name);

    // Delete the configuration object.
    $config->delete();

    // Verify the configuration object is empty.
    $this->assertIdentical($config->get(), []);
    $this->assertIdentical($config->isNew(), TRUE);

    // Verify that all copies of the configuration has been removed from the
    // static cache.
    $this->assertIdentical($config_factory->getEditable($name)->isNew(), TRUE);

    // Verify the active configuration contains no value.
    $actual_data = $storage->read($name);
    $this->assertIdentical($actual_data, FALSE);

    // Verify $this->config() returns no data.
    $new_config = $this->config($name);
    $this->assertIdentical($new_config->get(), $config->get());
    $this->assertIdentical($config->isNew(), TRUE);

    // Re-create the configuration object.
    $config->set('value', 're-created');
    $config->save();
    $this->assertIdentical($config->isNew(), FALSE);

    // Verify the active configuration contains the updated value.
    $actual_data = $storage->read($name);
    $this->assertIdentical($actual_data, ['value' => 're-created']);

    // Verify a call to $this->config() immediately returns the updated value.
    $new_config = $this->config($name);
    $this->assertIdentical($new_config->get(), $config->get());
    $this->assertIdentical($config->isNew(), FALSE);

    // Rename the configuration object.
    $new_name = 'config_test.crud_rename';
    $this->container->get('config.factory')->rename($name, $new_name);
    $renamed_config = $this->config($new_name);
    $this->assertIdentical($renamed_config->get(), $config->get());
    $this->assertIdentical($renamed_config->isNew(), FALSE);

    // Ensure that the old configuration object is removed from both the cache
    // and the configuration storage.
    $config = $this->config($name);
    $this->assertIdentical($config->get(), []);
    $this->assertIdentical($config->isNew(), TRUE);

    // Test renaming when config.factory does not have the object in its static
    // cache.
    $name = 'config_test.crud_rename';
    // Pollute the non-overrides static cache.
    $config_factory->getEditable($name);
    // Pollute the overrides static cache.
    $config = $config_factory->get($name);
    // Rename and ensure that happened properly.
    $new_name = 'config_test.crud_rename_no_cache';
    $config_factory->rename($name, $new_name);
    $renamed_config = $config_factory->get($new_name);
    $this->assertIdentical($renamed_config->get(), $config->get());
    $this->assertIdentical($renamed_config->isNew(), FALSE);
    // Ensure the overrides static cache has been cleared.
    $this->assertIdentical($config_factory->get($name)->isNew(), TRUE);
    // Ensure the non-overrides static cache has been cleared.
    $this->assertIdentical($config_factory->getEditable($name)->isNew(), TRUE);

    // Merge data into the configuration object.
    $new_config = $this->config($new_name);
    $expected_values = [
      'value' => 'herp',
      '404' => 'derp',
    ];
    $new_config->merge($expected_values);
    $new_config->save();
    $this->assertIdentical($new_config->get('value'), $expected_values['value']);
    $this->assertIdentical($new_config->get('404'), $expected_values['404']);

    // Test that getMultiple() does not return new config objects that were
    // previously accessed with get()
    $new_config = $config_factory->get('non_existing_key');
    $this->assertTrue($new_config->isNew());
    $this->assertEqual(0, count($config_factory->loadMultiple(['non_existing_key'])), 'loadMultiple() does not return new objects');
  }

  /**
   * Tests the validation of configuration object names.
   */
  public function testNameValidation() {
    // Verify that an object name without namespace causes an exception.
    $name = 'nonamespace';
    $message = 'Expected ConfigNameException was thrown for a name without a namespace.';
    try {
      $this->config($name)->save();
      $this->fail($message);
    }
    catch (ConfigNameException $e) {
      $this->pass($message);
    }

    // Verify that a name longer than the maximum length causes an exception.
    $name = 'config_test.herman_melville.moby_dick_or_the_whale.harper_1851.now_small_fowls_flew_screaming_over_the_yet_yawning_gulf_a_sullen_white_surf_beat_against_its_steep_sides_then_all_collapsed_and_the_great_shroud_of_the_sea_rolled_on_as_it_rolled_five_thousand_years_ago';
    $message = 'Expected ConfigNameException was thrown for a name longer than Config::MAX_NAME_LENGTH.';
    try {
      $this->config($name)->save();
      $this->fail($message);
    }
    catch (ConfigNameException $e) {
      $this->pass($message);
    }

    // Verify that disallowed characters in the name cause an exception.
    $characters = $test_characters = [':', '?', '*', '<', '>', '"', '\'', '/', '\\'];
    foreach ($test_characters as $i => $c) {
      try {
        $name = 'namespace.object' . $c;
        $config = $this->config($name);
        $config->save();
      }
      catch (ConfigNameException $e) {
        unset($test_characters[$i]);
      }
    }
    $this->assertTrue(empty($test_characters), format_string('Expected ConfigNameException was thrown for all invalid name characters: @characters', [
      '@characters' => implode(' ', $characters),
    ]));

    // Verify that a valid config object name can be saved.
    $name = 'namespace.object';
    $message = 'ConfigNameException was not thrown for a valid object name.';
    try {
      $config = $this->config($name);
      $config->save();
      $this->pass($message);
    }
    catch (ConfigNameException $e) {
      $this->fail($message);
    }

  }

  /**
   * Tests the validation of configuration object values.
   */
  public function testValueValidation() {
    // Verify that setData() will catch dotted keys.
    $message = 'Expected ConfigValueException was thrown from setData() for value with dotted keys.';
    try {
      $this->config('namespace.object')->setData(['key.value' => 12])->save();
      $this->fail($message);
    }
    catch (ConfigValueException $e) {
      $this->pass($message);
    }

    // Verify that set() will catch dotted keys.
    $message = 'Expected ConfigValueException was thrown from set() for value with dotted keys.';
    try {
      $this->config('namespace.object')->set('foo', ['key.value' => 12])->save();
      $this->fail($message);
    }
    catch (ConfigValueException $e) {
      $this->pass($message);
    }
  }

  /**
   * Tests data type handling.
   */
  public function testDataTypes() {
    \Drupal::service('module_installer')->install(['config_test']);
    $storage = new DatabaseStorage($this->container->get('database'), 'config');
    $name = 'config_test.types';
    $config = $this->config($name);
    $original_content = file_get_contents(drupal_get_path('module', 'config_test') . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY . "/$name.yml");
    $this->verbose('<pre>' . $original_content . "\n" . var_export($storage->read($name), TRUE));

    // Verify variable data types are intact.
    $data = [
      'array' => [],
      'boolean' => TRUE,
      'exp' => 1.2e+34,
      'float' => 3.14159,
      'float_as_integer' => (float) 1,
      'hex' => 0xC,
      'int' => 99,
      'octal' => 0775,
      'string' => 'string',
      'string_int' => '1',
    ];
    $data['_core']['default_config_hash'] = Crypt::hashBase64(serialize($data));
    $this->assertIdentical($config->get(), $data);

    // Re-set each key using Config::set().
    foreach ($data as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
    $this->assertIdentical($config->get(), $data);
    // Assert the data against the file storage.
    $this->assertIdentical($storage->read($name), $data);
    $this->verbose('<pre>' . $name . var_export($storage->read($name), TRUE));

    // Set data using config::setData().
    $config->setData($data)->save();
    $this->assertIdentical($config->get(), $data);
    $this->assertIdentical($storage->read($name), $data);

    // Test that schema type enforcement can be overridden by trusting the data.
    $this->assertSame(99, $config->get('int'));
    $config->set('int', '99')->save(TRUE);
    $this->assertSame('99', $config->get('int'));
    // Test that re-saving without testing the data enforces the schema type.
    $config->save();
    $this->assertSame($data, $config->get());

    // Test that setting an unsupported type for a config object with a schema
    // fails.
    try {
      $config->set('stream', fopen(__FILE__, 'r'))->save();
      $this->fail('No Exception thrown upon saving invalid data type.');
    }
    catch (UnsupportedDataTypeConfigException $e) {
      $this->pass(new FormattableMarkup('%class thrown upon saving invalid data type.', [
        '%class' => get_class($e),
      ]));
    }

    // Test that setting an unsupported type for a config object with no schema
    // also fails.
    $typed_config_manager = $this->container->get('config.typed');
    $config_name = 'config_test.no_schema';
    $config = $this->config($config_name);
    $this->assertFalse($typed_config_manager->hasConfigSchema($config_name));

    try {
      $config->set('stream', fopen(__FILE__, 'r'))->save();
      $this->fail('No Exception thrown upon saving invalid data type.');
    }
    catch (UnsupportedDataTypeConfigException $e) {
      $this->pass(new FormattableMarkup('%class thrown upon saving invalid data type.', [
        '%class' => get_class($e),
      ]));
    }
  }

}
