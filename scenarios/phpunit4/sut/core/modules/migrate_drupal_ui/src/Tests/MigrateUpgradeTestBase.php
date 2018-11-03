<?php

namespace Drupal\migrate_drupal_ui\Tests;

@trigger_error('\Drupal\migrate_drupal_ui\Tests\MigrateUpgradeTestBase is deprecated in Drupal 8.4.0 and will be removed before Drupal 9.0.0. Use \Drupal\Tests\migrate_drupal_ui\Functional\MigrateUpgradeTestBase instead.', E_USER_DEPRECATED);

use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate_drupal\MigrationConfigurationTrait;
use Drupal\simpletest\WebTestBase;

/**
 * Provides a base class for testing migration upgrades in the UI.
 *
 * @deprecated in Drupal 8.4.0 and will be removed before Drupal 9.0.0. Use
 *   \Drupal\Tests\migrate_drupal_ui\Functional\MigrateUpgradeTestBase instead.
 */
abstract class MigrateUpgradeTestBase extends WebTestBase {
  use MigrationConfigurationTrait;

  /**
   * Use the Standard profile to test help implementations of many core modules.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * The source database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $sourceDatabase;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'language',
    'content_translation',
    'migrate_drupal_ui',
    'telephone',
    'aggregator',
    'book',
    'forum',
    'statistics',
    'modules_available_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->createMigrationConnection();
    $this->sourceDatabase = Database::getConnection('default', 'migrate_drupal_ui');

    // Log in as user 1. Migrations in the UI can only be performed as user 1.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Loads a database fixture into the source database connection.
   *
   * @param string $path
   *   Path to the dump file.
   */
  protected function loadFixture($path) {
    $default_db = Database::getConnection()->getKey();
    Database::setActiveConnection($this->sourceDatabase->getKey());

    if (substr($path, -3) == '.gz') {
      $path = 'compress.zlib://' . $path;
    }
    require $path;

    Database::setActiveConnection($default_db);
  }

  /**
   * Changes the database connection to the prefixed one.
   *
   * @todo Remove when we don't use global. https://www.drupal.org/node/2552791
   */
  protected function createMigrationConnection() {
    $connection_info = Database::getConnectionInfo('default')['default'];
    if ($connection_info['driver'] === 'sqlite') {
      // Create database file in the test site's public file directory so that
      // \Drupal\simpletest\TestBase::restoreEnvironment() will delete this once
      // the test is complete.
      $file = $this->publicFilesDirectory . '/' . $this->testId . '-migrate.db.sqlite';
      touch($file);
      $connection_info['database'] = $file;
      $connection_info['prefix'] = '';
    }
    else {
      $prefix = is_array($connection_info['prefix']) ? $connection_info['prefix']['default'] : $connection_info['prefix'];
      // Simpletest uses fixed length prefixes. Create a new prefix for the
      // source database. Adding to the end of the prefix ensures that
      // \Drupal\simpletest\TestBase::restoreEnvironment() will remove the
      // additional tables.
      $connection_info['prefix'] = $prefix . '0';
    }

    Database::addConnectionInfo('migrate_drupal_ui', 'default', $connection_info);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    Database::removeConnection('migrate_drupal_ui');
    parent::tearDown();
  }

  /**
   * Executes all steps of migrations upgrade.
   */
  public function testMigrateUpgrade() {
    $connection_options = $this->sourceDatabase->getConnectionOptions();
    $this->drupalGet('/upgrade');
    $this->assertText('Upgrade a site by importing its files and the data from its database into a clean and empty new install of Drupal 8.');

    $this->drupalPostForm(NULL, [], t('Continue'));
    $this->assertText('Provide credentials for the database of the Drupal site you want to upgrade.');
    $this->assertFieldByName('mysql[host]');

    $driver = $connection_options['driver'];
    $connection_options['prefix'] = $connection_options['prefix']['default'];

    // Use the driver connection form to get the correct options out of the
    // database settings. This supports all of the databases we test against.
    $drivers = drupal_get_database_types();
    $form = $drivers[$driver]->getFormOptions($connection_options);
    $connection_options = array_intersect_key($connection_options, $form + $form['advanced_options']);
    $edit = [
      $driver => $connection_options,
      'source_base_path' => $this->getSourceBasePath(),
    ];
    if (count($drivers) !== 1) {
      $edit['driver'] = $driver;
    }
    $edits = $this->translatePostValues($edit);

    // Ensure submitting the form with invalid database credentials gives us a
    // nice warning.
    $this->drupalPostForm(NULL, [$driver . '[database]' => 'wrong'] + $edits, t('Review upgrade'));
    $this->assertText('Resolve the issue below to continue the upgrade.');

    $this->drupalPostForm(NULL, $edits, t('Review upgrade'));
    $this->assertResponse(200);
    $this->assertText('Upgrade analysis report');
    // Ensure we get errors about missing modules.
    $this->assertText(t('Source module not found for module_no_annotation.'));
    $this->assertText(t('Source module not found for modules_available_test.'));
    $this->assertText(t('Destination module not found for modules_available_test'));

    // Uninstall the module causing the missing module error messages.
    $this->container->get('module_installer')->uninstall(['modules_available_test'], TRUE);

    // Restart the upgrade process.
    $this->drupalGet('/upgrade');
    $this->assertText('Upgrade a site by importing its files and the data from its database into a clean and empty new install of Drupal 8.');

    $this->drupalPostForm(NULL, [], t('Continue'));
    $this->assertText('Provide credentials for the database of the Drupal site you want to upgrade.');
    $this->assertFieldByName('mysql[host]');

    $this->drupalPostForm(NULL, $edits, t('Review upgrade'));
    $this->assertResponse(200);
    $this->assertText('Upgrade analysis report');
    // Ensure there are no errors about the missing modules.
    $this->assertNoText(t('Source module not found for module_no_annotation.'));
    $this->assertNoText(t('Source module not found for modules_available_test.'));
    $this->assertNoText(t('Destination module not found for modules_available_test'));
    // Check for any missing module errors.
    $this->drupalPostForm(NULL, [], t('Perform upgrade'));
    $this->assertText(t('Congratulations, you upgraded Drupal!'));

    // Have to reset all the static caches after migration to ensure entities
    // are loadable.
    $this->resetAll();

    $expected_counts = $this->getEntityCounts();
    foreach (array_keys(\Drupal::entityTypeManager()
      ->getDefinitions()) as $entity_type) {
      $real_count = \Drupal::entityQuery($entity_type)->count()->execute();
      $expected_count = isset($expected_counts[$entity_type]) ? $expected_counts[$entity_type] : 0;
      $this->assertEqual($expected_count, $real_count, "Found $real_count $entity_type entities, expected $expected_count.");
    }

    $version_tag = 'Drupal ' . $this->getLegacyDrupalVersion($this->sourceDatabase);
    $plugin_manager = \Drupal::service('plugin.manager.migration');
    /** @var \Drupal\migrate\Plugin\Migration[] $all_migrations */
    $all_migrations = $plugin_manager->createInstancesByTag($version_tag);
    foreach ($all_migrations as $migration) {
      $id_map = $migration->getIdMap();
      foreach ($id_map as $source_id => $map) {
        // Convert $source_id into a keyless array so that
        // \Drupal\migrate\Plugin\migrate\id_map\Sql::getSourceHash() works as
        // expected.
        $source_id_values = array_values(unserialize($source_id));
        $row = $id_map->getRowBySource($source_id_values);
        $destination = serialize($id_map->currentDestination());
        $message = "Migration of $source_id to $destination as part of the {$migration->id()} migration. The source row status is " . $row['source_row_status'];
        // A completed migration should have maps with
        // MigrateIdMapInterface::STATUS_IGNORED or
        // MigrateIdMapInterface::STATUS_IMPORTED.
        if ($row['source_row_status'] == MigrateIdMapInterface::STATUS_FAILED || $row['source_row_status'] == MigrateIdMapInterface::STATUS_NEEDS_UPDATE) {
          $this->fail($message);
        }
        else {
          $this->pass($message);
        }
      }
    }
    \Drupal::service('module_installer')->install(['forum']);
    \Drupal::service('module_installer')->install(['book']);
  }

  /**
   * Gets the source base path for the concrete test.
   *
   * @return string
   *   The source base path.
   */
  abstract protected function getSourceBasePath();

  /**
   * Gets the expected number of entities per entity type after migration.
   *
   * @return int[]
   *   An array of expected counts keyed by entity type ID.
   */
  abstract protected function getEntityCounts();

}
