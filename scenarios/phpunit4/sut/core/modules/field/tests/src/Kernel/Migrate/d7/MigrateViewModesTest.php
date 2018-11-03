<?php

namespace Drupal\Tests\field\Kernel\Migrate\d7;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityViewModeInterface;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of D7 view modes.
 *
 * @group field
 */
class MigrateViewModesTest extends MigrateDrupal7TestBase {

  public static $modules = ['comment', 'node', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('comment');
    $this->installEntitySchema('node');
    $this->executeMigration('d7_view_modes');
  }

  /**
   * Asserts various aspects of a view mode entity.
   *
   * @param string $id
   *   The entity ID.
   * @param string $label
   *   The expected label of the view mode.
   * @param string $entity_type
   *   The expected entity type ID which owns the view mode.
   */
  protected function assertEntity($id, $label, $entity_type) {
    /** @var \Drupal\Core\Entity\EntityViewModeInterface $view_mode */
    $view_mode = EntityViewMode::load($id);
    $this->assertTrue($view_mode instanceof EntityViewModeInterface);
    $this->assertIdentical($label, $view_mode->label());
    $this->assertIdentical($entity_type, $view_mode->getTargetType());
  }

  /**
   * Tests migration of D7 view mode variables to D8 config entities.
   */
  public function testMigration() {
    $this->assertEntity('comment.full', 'Full', 'comment');
    $this->assertEntity('node.teaser', 'Teaser', 'node');
    $this->assertEntity('node.full', 'Full', 'node');
    $this->assertEntity('node.custom', 'custom', 'node');
    $this->assertEntity('user.full', 'Full', 'user');
  }

}
