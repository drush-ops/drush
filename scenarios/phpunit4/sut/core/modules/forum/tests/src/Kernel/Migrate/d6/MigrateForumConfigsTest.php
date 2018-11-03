<?php

namespace Drupal\Tests\forum\Kernel\Migrate\d6;

use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Upgrade variables to forum.settings.yml.
 *
 * @group migrate_drupal_6
 */
class MigrateForumConfigsTest extends MigrateDrupal6TestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['comment', 'forum', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->executeMigration('d6_taxonomy_vocabulary');
    $this->executeMigration('d6_forum_settings');
  }

  /**
   * Tests migration of forum variables to forum.settings.yml.
   */
  public function testForumSettings() {
    $config = $this->config('forum.settings');
    $this->assertIdentical(15, $config->get('topics.hot_threshold'));
    $this->assertIdentical(25, $config->get('topics.page_limit'));
    $this->assertIdentical(1, $config->get('topics.order'));
    $this->assertIdentical('forums', $config->get('vocabulary'));
    // This is 'forum_block_num_0' in D6, but block:active:limit' in D8.
    $this->assertSame(3, $config->get('block.active.limit'));
    // This is 'forum_block_num_1' in D6, but 'block:new:limit' in D8.
    $this->assertSame(4, $config->get('block.new.limit'));
    $this->assertConfigSchema(\Drupal::service('config.typed'), 'forum.settings', $config->get());
  }

}
