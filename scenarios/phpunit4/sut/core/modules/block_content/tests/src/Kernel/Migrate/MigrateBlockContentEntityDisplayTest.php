<?php

namespace Drupal\Tests\block_content\Kernel\Migrate;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of block content body field display configuration.
 *
 * @group block_content
 */
class MigrateBlockContentEntityDisplayTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'block_content', 'filter', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->executeMigrations([
      'block_content_type',
      'block_content_body_field',
      'block_content_entity_display',
    ]);
  }

  /**
   * Asserts a display entity.
   *
   * @param string $id
   *   The entity ID.
   * @param string $component_id
   *   The ID of the display component.
   */
  protected function assertDisplay($id, $component_id) {
    $component = EntityViewDisplay::load($id)->getComponent($component_id);
    $this->assertInternalType('array', $component);
    $this->assertSame('hidden', $component['label']);
  }

  /**
   * Tests the migrated display configuration.
   */
  public function testMigration() {
    $this->assertDisplay('block_content.basic.default', 'body');
  }

}
