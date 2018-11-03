<?php

namespace Drupal\Tests\block_content\Kernel\Migrate;

use Drupal\block_content\BlockContentTypeInterface;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of the basic block content type.
 *
 * @group block_content
 */
class MigrateBlockContentTypeTest extends MigrateDrupal7TestBase {

  public static $modules = ['block', 'block_content', 'filter', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['block_content']);
    $this->installEntitySchema('block_content');
    $this->executeMigration('block_content_type');
  }

  /**
   * Tests the block content type migration.
   */
  public function testBlockContentTypeMigration() {
    /** @var \Drupal\block_content\BlockContentTypeInterface $entity */
    $entity = BlockContentType::load('basic');
    $this->assertTrue($entity instanceof BlockContentTypeInterface);
    $this->assertIdentical('Basic', $entity->label());
  }

}
