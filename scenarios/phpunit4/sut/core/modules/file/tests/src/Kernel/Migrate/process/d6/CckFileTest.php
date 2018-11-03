<?php

namespace Drupal\Tests\file\Kernel\Migrate\process\d6;

use Drupal\file\Plugin\migrate\process\d6\CckFile;
use Drupal\migrate\Plugin\Migration;
use Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase;

/**
 * Cck file field migration.
 *
 * @coversDefaultClass \Drupal\file\Plugin\migrate\process\d6\CckFile
 *
 * @group file
 * @group legacy
 */
class CckFileTest extends MigrateDrupalTestBase {

  /**
   * Tests configurability of file migration name.
   *
   * @covers ::__construct
   * @expectedDeprecation CckFile is deprecated in Drupal 8.3.x and will be be removed before Drupal 9.0.x. Use \Drupal\file\Plugin\migrate\process\d6\FieldFile instead.
   */
  public function testConfigurableFileMigration() {
    $migration = Migration::create($this->container, [], 'custom_migration', []);
    $cck_file_migration = CckFile::create($this->container, ['migration' => 'custom_file'], 'custom_file', [], $migration);
    $migration_plugin = $this->readAttribute($cck_file_migration, 'migrationPlugin');
    $config = $this->readAttribute($migration_plugin, 'configuration');

    $this->assertEquals($config['migration'], 'custom_file');
  }

}
