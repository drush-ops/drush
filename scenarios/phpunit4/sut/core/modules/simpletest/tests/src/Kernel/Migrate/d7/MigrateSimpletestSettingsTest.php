<?php

namespace Drupal\Tests\simpletest\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of SimpleTest's variables to configuration.
 *
 * @group simpletest
 */
class MigrateSimpletestSettingsTest extends MigrateDrupal7TestBase {

  public static $modules = ['simpletest'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->executeMigration('d7_simpletest_settings');
  }

  /**
   * Tests migration of SimpleTest settings to configuration.
   */
  public function testMigration() {
    $config = \Drupal::config('simpletest.settings')->get();
    $this->assertTrue($config['clear_results']);
    $this->assertIdentical(CURLAUTH_BASIC, $config['httpauth']['method']);
    $this->assertIdentical('testbot', $config['httpauth']['username']);
    $this->assertIdentical('foobaz', $config['httpauth']['password']);
    $this->assertTrue($config['verbose']);
  }

}
