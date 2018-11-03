<?php

namespace Drupal\Tests\menu_ui\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Reorder menu items.
 *
 * @group menu_ui
 */
class MenuLinkReorderTest extends BrowserTestBase {

  /**
   * An administrator user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $administrator;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['menu_ui', 'test_page_test', 'node', 'block'];

  /**
   * Test creating, editing, deleting menu links via node form widget.
   */
  public function testDefaultMenuLinkReorder() {

    // Add the main menu block.
    $this->drupalPlaceBlock('system_menu_block:main');

    // Assert that the Home link is available.
    $this->drupalGet('test-page');
    $this->assertLink('Home');

    // The administrator user that can re-order menu links.
    $this->administrator = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'administer menu',
    ]);
    $this->drupalLogin($this->administrator);

    // Change the weight of the link to a non default value.
    $edit = [
      'links[menu_plugin_id:test_page_test.front_page][weight]' => -10,
    ];
    $this->drupalPostForm('admin/structure/menu/manage/main', $edit, t('Save'));

    // The link is still there.
    $this->drupalGet('test-page');
    $this->assertLink('Home');

    // Clear all caches.
    $this->drupalPostForm('admin/config/development/performance', [], t('Clear all caches'));

    // Clearing all caches should not affect the state of the menu link.
    $this->drupalGet('test-page');
    $this->assertLink('Home');

  }

}
