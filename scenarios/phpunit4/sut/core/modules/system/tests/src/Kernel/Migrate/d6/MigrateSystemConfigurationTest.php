<?php

namespace Drupal\Tests\system\Kernel\Migrate\d6;

use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Migrates various configuration objects owned by the System module.
 *
 * @group migrate_drupal_6
 */
class MigrateSystemConfigurationTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['action', 'file', 'system'];

  protected $expectedConfig = [
    'system.cron' => [
      'threshold' => [
        'requirements_warning' => 172800,
        'requirements_error' => 1209600,
      ],
      // logging is not handled by the migration.
      'logging' => 1,
    ],
    'system.date' => [
      // country is not handled by the migration.
      'country' => [
        'default' => '',
      ],
      'first_day' => 4,
      // timezone is not handled by the migration.
      'timezone' => [
        'default' => 'Europe/Paris',
        'user' => [
          'configurable' => FALSE,
          // warn is not handled by the migration.
          'warn' => FALSE,
          // default is not handled by the migration.
          'default' => 0,
        ],
      ],
    ],
    'system.file' => [
      'allow_insecure_uploads' => TRUE,
      // default_scheme is not handled by the migration.
      'default_scheme' => 'public',
      'path' => [
        'temporary' => 'files/temp',
      ],
      // temporary_maximum_age is not handled by the migration.
      'temporary_maximum_age' => 21600,
    ],
    'system.image.gd' => [
      'jpeg_quality' => 75,
    ],
    'system.image' => [
      'toolkit' => 'gd',
    ],
    'system.logging' => [
      'error_level' => 'some',
    ],
    'system.maintenance' => [
      'message' => 'Drupal is currently under maintenance. We should be back shortly. Thank you for your patience.',
      // langcode is not handled by the migration.
      'langcode' => 'en',
    ],
    'system.performance' => [
      'cache' => [
        'page' => [
          'max_age' => 0,
        ],
      ],
      'css' => [
        'preprocess' => FALSE,
        // gzip is not handled by the migration.
        'gzip' => TRUE,
      ],
      // fast_404 is not handled by the migration.
      'fast_404' => [
        'enabled' => TRUE,
        'paths' => '/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i',
        'exclude_paths' => '/\/(?:styles|imagecache)\//',
        'html' => '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>',
      ],
      'js' => [
        'preprocess' => FALSE,
        // gzip is not handled by the migration.
        'gzip' => TRUE,
      ],
      // stale_file_threshold is not handled by the migration.
      'stale_file_threshold' => 2592000,
    ],
    'system.rss' => [
      // channel is not handled by the migration.
      'channel' => [
        'description' => '',
      ],
      'items' => [
        'limit' => 10,
        'view_mode' => 'title',
      ],
      // langcode is not handled by the migration.
      'langcode' => 'en',
    ],
    'system.site' => [
      // uuid is not handled by the migration.
      'uuid' => '',
      'name' => 'site_name',
      'mail' => 'site_mail@example.com',
      'slogan' => 'Migrate rocks',
      'page' => [
        '403' => '/user',
        '404' => '/page-not-found',
        'front' => '/node',
      ],
      'admin_compact_mode' => FALSE,
      'weight_select_max' => 100,
      // langcode and default_langcode are not handled by the migration.
      'langcode' => 'en',
      'default_langcode' => 'en',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $migrations = [
      'd6_system_cron',
      'd6_system_date',
      'd6_system_file',
      'system_image_gd',
      'system_image',
      'system_logging',
      'system_maintenance',
      'd6_system_performance',
      'system_rss',
      'system_site',
    ];
    $this->executeMigrations($migrations);
  }

  /**
   * Tests that all expected configuration gets migrated.
   */
  public function testConfigurationMigration() {
    foreach ($this->expectedConfig as $config_id => $values) {
      $actual = \Drupal::config($config_id)->get();
      unset($actual['_core']);
      $this->assertSame($actual, $values, $config_id . ' matches expected values.');
    }
  }

}
