<?php

namespace Drupal\Tests\contact\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of Contact settings to configuration.
 *
 * @group migrate_drupal_7
 */
class MigrateContactSettingsTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['contact'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->executeMigration('contact_category');
    $this->executeMigration('d7_contact_settings');
  }

  /**
   * Tests migration of Contact's variables to configuration.
   */
  public function testContactSettings() {
    $config = $this->config('contact.settings');
    $this->assertTrue($config->get('user_default_enabled'));
    $this->assertIdentical(33, $config->get('flood.limit'));
    $this->assertEqual('website_testing', $config->get('default_form'));
  }

}
