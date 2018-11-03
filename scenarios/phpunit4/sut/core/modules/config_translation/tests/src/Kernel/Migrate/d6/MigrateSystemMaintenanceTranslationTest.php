<?php

namespace Drupal\Tests\config_translation\Kernel\Migrate\d6;

use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Upgrade i18n maintenance variables to system.*.yml.
 *
 * @group migrate_drupal_6
 * @group legacy
 */
class MigrateSystemMaintenanceTranslationTest extends MigrateDrupal6TestBase {

  public static $modules = [
    'language',
    'config_translation',
    // Required for translation migrations.
    'migrate_drupal_multilingual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->executeMigration('d6_system_maintenance_translation');
  }

  /**
   * Tests migration of system (maintenance) variables to system.maintenance.yml.
   */
  public function testSystemMaintenance() {
    $config = \Drupal::service('language_manager')->getLanguageConfigOverride('fr', 'system.maintenance');
    $this->assertIdentical('fr - Drupal is currently under maintenance. We should be back shortly. Thank you for your patience.', $config->get('message'));
  }

}
