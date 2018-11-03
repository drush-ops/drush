<?php

namespace Drupal\Tests\block\Kernel\Migrate\d7;

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of blocks to configuration entities.
 *
 * @group block
 */
class MigrateBlockTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'views',
    'comment',
    'menu_ui',
    'block_content',
    'node',
    'text',
    'filter',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Install the themes used for this test.
    $this->container->get('theme_installer')->install(['bartik', 'seven']);

    $this->installConfig(static::$modules);
    $this->installEntitySchema('block_content');

    // Set Bartik and Seven as the default public and admin theme.
    $config = $this->config('system.theme');
    $config->set('default', 'bartik');
    $config->set('admin', 'seven');
    $config->save();

    $this->executeMigrations([
      'd7_filter_format',
      'd7_user_role',
      'block_content_type',
      'block_content_body_field',
      'd7_custom_block',
      'd7_block',
    ]);
    block_rebuild();
  }

  /**
   * Asserts various aspects of a block.
   *
   * @param string $id
   *   The block ID.
   * @param string $plugin_id
   *   The block's plugin ID.
   * @param array $roles
   *   Role IDs the block is expected to have.
   * @param string $pages
   *   The list of pages on which the block should appear.
   * @param string $region
   *   The display region.
   * @param string $theme
   *   The theme.
   * @param string $weight
   *   The block weight.
   * @param string $label
   *   The block label.
   * @param string $label_display
   *   The block label display setting.
   * @param bool $status
   *   Whether the block is expected to be enabled or disabled.
   */
  public function assertEntity($id, $plugin_id, array $roles, $pages, $region, $theme, $weight, $label, $label_display, $status = TRUE) {
    $block = Block::load($id);
    $this->assertTrue($block instanceof Block);
    /** @var \Drupal\block\BlockInterface $block */
    $this->assertSame($plugin_id, $block->getPluginId());

    $visibility = $block->getVisibility();
    if ($roles) {
      $this->assertSame($roles, array_values($visibility['user_role']['roles']));
      $this->assertSame('@user.current_user_context:current_user', $visibility['user_role']['context_mapping']['user']);
    }
    if ($pages) {
      $this->assertSame($pages, $visibility['request_path']['pages']);
    }

    $this->assertSame($region, $block->getRegion());
    $this->assertSame($theme, $block->getTheme());
    $this->assertSame($weight, $block->getWeight());
    $this->assertSame($status, $block->status());

    $config = $this->config('block.block.' . $id);
    $this->assertSame($label, $config->get('settings.label'));
    $this->assertSame($label_display, $config->get('settings.label_display'));
  }

  /**
   * Tests the block migration.
   */
  public function testBlockMigration() {
    $this->assertEntity('bartik_system_main', 'system_main_block', [], '', 'content', 'bartik', 0, '', '0');
    $this->assertEntity('bartik_search_form', 'search_form_block', [], '', 'sidebar_first', 'bartik', -1, '', '0');
    $this->assertEntity('bartik_user_login', 'user_login_block', [], '', 'sidebar_first', 'bartik', 0, '', '0');
    $this->assertEntity('bartik_system_powered_by', 'system_powered_by_block', [], '', 'footer_fifth', 'bartik', 10, '', '0');
    $this->assertEntity('seven_system_main', 'system_main_block', [], '', 'content', 'seven', 0, '', '0');
    $this->assertEntity('seven_user_login', 'user_login_block', [], '', 'content', 'seven', 10, '', '0');

    // The d7_custom_block migration should have migrated a block containing a
    // mildly amusing limerick. We'll need its UUID to determine
    // bartik_block_1's plugin ID.
    $uuid = BlockContent::load(1)->uuid();
    $this->assertEntity('bartik_block_1', 'block_content:' . $uuid, ['authenticated'], '', 'highlighted', 'bartik', 0, 'Mildly amusing limerick of the day', 'visible');

    // Assert that disabled blocks (or enabled blocks whose plugin IDs could
    // be resolved) did not migrate.
    $non_existent_blocks = [
      'bartik_system_navigation',
      'bartik_system_help',
      'seven_user_new',
      'seven_search_form',
      'bartik_comment_recent',
      'bartik_node_syndicate',
      'bartik_node_recent',
      'bartik_shortcut_shortcuts',
      'bartik_system_management',
      'bartik_system_user-menu',
      'bartik_system_main-menu',
      'bartik_user_new',
      'bartik_user_online',
      'seven_comment_recent',
      'seven_node_syndicate',
      'seven_shortcut_shortcuts',
      'seven_system_powered-by',
      'seven_system_navigation',
      'seven_system_management',
      'seven_system_user-menu',
      'seven_system_main-menu',
      'seven_user_online',
      'bartik_blog_recent',
      'bartik_book_navigation',
      'bartik_locale_language',
      'bartik_forum_active',
      'bartik_forum_new',
      'seven_blog_recent',
      'seven_book_navigation',
      'seven_locale_language',
      'seven_forum_active',
      'seven_forum_new',
      'bartik_menu_menu-test-menu',
      'bartik_statistics_popular',
      'seven_menu_menu-test-menu',
      'seven_statistics_popular',
      'seven_block_1',
    ];
    $this->assertTrue(empty(Block::loadMultiple($non_existent_blocks)));
  }

}
