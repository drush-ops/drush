<?php

namespace Drupal\Tests\menu_ui\Functional;

use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\system\Functional\Cache\PageCacheTagsTestBase;
use Drupal\system\Entity\Menu;

/**
 * Tests the Menu and Menu Link entities' cache tags.
 *
 * @group menu_ui
 */
class MenuCacheTagsTest extends PageCacheTagsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['menu_ui', 'block', 'test_page_test'];

  /**
   * Tests cache tags presence and invalidation of the Menu entity.
   *
   * Tests the following cache tags:
   * - "menu:<menu ID>"
   */
  public function testMenuBlock() {
    $url = Url::fromRoute('test_page_test.test_page');

    // Create a Llama menu, add a link to it and place the corresponding block.
    $menu = Menu::create([
      'id' => 'llama',
      'label' => 'Llama',
      'description' => 'Description text',
    ]);
    $menu->save();
    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager */
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    // Move a link into the new menu.
    $menu_link = $menu_link_manager->updateDefinition('test_page_test.test_page', ['menu_name' => 'llama', 'parent' => '']);
    $block = $this->drupalPlaceBlock('system_menu_block:llama', ['label' => 'Llama', 'provider' => 'system', 'region' => 'footer']);

    // Prime the page cache.
    $this->verifyPageCache($url, 'MISS');

    // Verify a cache hit, but also the presence of the correct cache tags.
    $expected_tags = [
      'http_response',
      'rendered',
      'block_view',
      'config:block_list',
      'config:block.block.' . $block->id(),
      'config:system.menu.llama',
      // The cache contexts associated with the (in)accessible menu links are
      // bubbled.
      'config:user.role.anonymous',
    ];
    $this->verifyPageCache($url, 'HIT', $expected_tags);

    // Verify that after modifying the menu, there is a cache miss.
    $this->pass('Test modification of menu.', 'Debug');
    $menu->set('label', 'Awesome llama');
    $menu->save();
    $this->verifyPageCache($url, 'MISS');

    // Verify a cache hit.
    $this->verifyPageCache($url, 'HIT');

    // Verify that after modifying the menu link weight, there is a cache miss.
    $menu_link_manager->updateDefinition('test_page_test.test_page', ['weight' => -10]);
    $this->pass('Test modification of menu link.', 'Debug');
    $this->verifyPageCache($url, 'MISS');

    // Verify a cache hit.
    $this->verifyPageCache($url, 'HIT');

    // Verify that after adding a menu link, there is a cache miss.
    $this->pass('Test addition of menu link.', 'Debug');
    $menu_link_2 = MenuLinkContent::create([
      'id' => '',
      'parent' => '',
      'title' => 'Alpaca',
      'menu_name' => 'llama',
      'link' => [
        ['uri' => 'internal:/'],
      ],
      'bundle' => 'menu_name',
    ]);
    $menu_link_2->save();
    $this->verifyPageCache($url, 'MISS');

    // Verify a cache hit.
    $this->verifyPageCache($url, 'HIT');

    // Verify that after resetting the first menu link, there is a cache miss.
    $this->pass('Test reset of menu link.', 'Debug');
    $this->assertTrue($menu_link->isResettable(), 'First link can be reset');
    $menu_link = $menu_link_manager->resetLink($menu_link->getPluginId());
    $this->verifyPageCache($url, 'MISS');

    // Verify a cache hit.
    $this->verifyPageCache($url, 'HIT', $expected_tags);

    // Verify that after deleting the menu, there is a cache miss.
    $this->pass('Test deletion of menu.', 'Debug');
    $menu->delete();
    $this->verifyPageCache($url, 'MISS');

    // Verify a cache hit.
    $this->verifyPageCache($url, 'HIT', ['config:block_list', 'config:user.role.anonymous', 'http_response', 'rendered']);
  }

}
