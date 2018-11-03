<?php

namespace Drupal\Tests\block_content\Kernel\Migrate;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of block content body field form display configuration.
 *
 * @group block_content
 */
class MigrateBlockContentEntityFormDisplayTest extends MigrateDrupal7TestBase {

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
      'block_content_entity_form_display',
    ]);
  }

  /**
   * Asserts a display entity.
   *
   * @param string $id
   *   The entity ID.
   * @param string $component_id
   *   The ID of the form component.
   */
  protected function assertDisplay($id, $component_id) {
    $component = EntityFormDisplay::load($id)->getComponent($component_id);
    $this->assertInternalType('array', $component);
    $this->assertSame('text_textarea_with_summary', $component['type']);
  }

  /**
   * Tests the migrated display configuration.
   */
  public function testMigration() {
    $this->assertDisplay('block_content.basic.default', 'body');
  }

}
