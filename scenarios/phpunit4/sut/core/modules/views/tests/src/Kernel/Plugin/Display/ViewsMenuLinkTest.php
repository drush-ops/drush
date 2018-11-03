<?php

namespace Drupal\Tests\views\Kernel\Plugin\Display;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;

/**
 * Menu link test.
 *
 * @group views
 */
class ViewsMenuLinkTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'menu_ui',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_page_display_menu'];

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The menu link overrides.
   *
   * @var \Drupal\Core\Menu\StaticMenuLinkOverridesInterface
   */
  protected $menuLinkOverrides;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->entityManager = $this->container->get('entity.manager');
    $this->menuLinkManager = $this->container->get('plugin.manager.menu.link');
    $this->menuLinkOverrides = $this->container->get('menu_link.static.overrides');
  }

  /**
   * Test views internal menu link options.
   */
  public function testMenuLinkOverrides() {
    // Link from views module.
    $views_link = $this->menuLinkManager->getDefinition('views_view:views.test_page_display_menu.page_3');
    $this->assertTrue($views_link['enabled'], 'Menu link is enabled.');
    $this->assertFalse($views_link['expanded'], 'Menu link is not expanded.');
    $views_link['enabled'] = 0;
    $views_link['expanded'] = 1;
    $this->menuLinkManager->updateDefinition($views_link['id'], $views_link);
    $views_link = $this->menuLinkManager->getDefinition($views_link['id']);
    $this->assertFalse($views_link['enabled'], 'Menu link is disabled.');
    $this->assertTrue($views_link['expanded'], 'Menu link is expanded.');
    $this->menuLinkManager->rebuild();
    $this->assertFalse($views_link['enabled'], 'Menu link is disabled.');
    $this->assertTrue($views_link['expanded'], 'Menu link is expanded.');

    // Link from user module.
    $user_link = $this->menuLinkManager->getDefinition('user.page');
    $this->assertTrue($user_link['enabled'], 'Menu link is enabled.');
    $user_link['enabled'] = 0;
    $views_link['expanded'] = 1;
    $this->menuLinkManager->updateDefinition($user_link['id'], $user_link);
    $this->assertFalse($user_link['enabled'], 'Menu link is disabled.');
    $this->menuLinkManager->rebuild();
    $this->assertFalse($user_link['enabled'], 'Menu link is disabled.');

    $this->menuLinkOverrides->reload();

    $views_link = $this->menuLinkManager->getDefinition('views_view:views.test_page_display_menu.page_3');
    $this->assertFalse($views_link['enabled'], 'Menu link is disabled.');
    $this->assertTrue($views_link['expanded'], 'Menu link is expanded.');

    $user_link = $this->menuLinkManager->getDefinition('user.page');
    $this->assertFalse($user_link['enabled'], 'Menu link is disabled.');
  }

}
