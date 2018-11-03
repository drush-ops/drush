<?php

namespace Drupal\KernelTests\Core\Config;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the listing of configuration entities.
 *
 * @group config
 */
class ConfigEntityNormalizeTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['config_test'];

  protected function setUp() {
    parent::setUp();
    $this->installConfig(static::$modules);
  }

  public function testNormalize() {
    $config_entity = \Drupal::entityTypeManager()->getStorage('config_test')->create(['id' => 'system', 'label' => 'foobar', 'weight' => 1]);
    $config_entity->save();

    // Modify stored config entity, this is comparable with a schema change.
    $config = $this->config('config_test.dynamic.system');
    $data = [
      'label' => 'foobar',
      'additional_key' => TRUE,
    ] + $config->getRawData();
    $config->setData($data)->save();
    $this->assertNotIdentical($config_entity->toArray(), $config->getRawData(), 'Stored config entity is not is equivalent to config schema.');

    $config_entity = entity_load('config_test', 'system', TRUE);
    $config_entity->save();

    $config = $this->config('config_test.dynamic.system');
    $this->assertIdentical($config_entity->toArray(), $config->getRawData(), 'Stored config entity is equivalent to config schema.');
  }

}
