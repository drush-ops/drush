<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests devel toolbar module functionality.
 *
 * @group devel
 */
class DevelToolbarTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel', 'toolbar', 'block'];

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $toolbarUser;

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $develUser;

  /**
   * The dafault toolbar items.
   *
   * @var array
   */
  protected $defaultToolbarItems = [
    'devel.cache_clear',
    'devel.container_info.service',
    'devel.admin_settings_link',
    'devel.execute_php',
    'devel.menu_rebuild',
    'devel.reinstall',
    'devel.route_info',
    'devel.run_cron',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->develUser = $this->drupalCreateUser([
      'administer site configuration',
      'access devel information',
      'execute php code',
      'access toolbar',
    ]);
    $this->toolbarUser = $this->drupalCreateUser([
      'access toolbar',
    ]);
  }

  /**
   * Tests configuration form.
   */
  public function testConfigurationForm() {
    // Ensures that the page is accessible ony to users with the adequate
    // permissions.
    $this->drupalGet('admin/config/development/devel/toolbar');
    $this->assertSession()->statusCodeEquals(403);

    // Ensures that the config page is accessible for users with the adequate
    // permissions and exists the Devel toolbar local task.
    $this->drupalLogin($this->develUser);
    $this->drupalGet('admin/config/development/devel/toolbar');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.tabs .primary a:contains("Toolbar Settings")');
    $this->assertSession()->pageTextContains('Devel Toolbar Settings');

    // Ensures and that all devel menu links are listed in the configuration
    // page.
    foreach ($this->getMenuLinkInfos() as $link) {
      $this->assertSession()->fieldExists(sprintf('toolbar_items[%s]', $link['id']));
    }

    // Ensures and that the default configuration items are selected by
    // default.
    foreach ($this->defaultToolbarItems as $item) {
      $this->assertSession()->checkboxChecked(sprintf('toolbar_items[%s]', $item));
    }

    // Ensures that the configuration save works as expected.
    $edit = [
      'toolbar_items[devel.event_info]' => 'devel.event_info',
      'toolbar_items[devel.theme_registry]' => 'devel.theme_registry',
    ];
    $this->drupalPostForm('admin/config/development/devel/toolbar', $edit, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $expected_items = array_merge($this->defaultToolbarItems, ['devel.event_info', 'devel.theme_registry']);
    sort($expected_items);
    $config_items = \Drupal::config('devel.toolbar.settings')->get('toolbar_items');
    sort($config_items);

    $this->assertEquals($expected_items, $config_items);
  }

  /**
   * Tests cache metadata headers.
   */
  public function testCacheHeaders() {
    // Disable user toolbar tab so we can test properly if the devel toolbar
    // implementation interferes with the page cacheability.
    \Drupal::service('module_installer')->install(['toolbar_disable_user_toolbar']);

    // The menu is not loaded for users without the adequate permission,
    // so no cache tags for configuration are added.
    $this->drupalLogin($this->toolbarUser);
    $this->assertSession()->responseHeaderNotContains('X-Drupal-Cache-Tags', 'config:devel.toolbar.settings');
    $this->assertSession()->responseHeaderNotContains('X-Drupal-Cache-Tags', 'config:system.menu.devel');

    // Make sure that the configuration cache tags are present for users with
    // the adequate permission.
    $this->drupalLogin($this->develUser);
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:devel.toolbar.settings');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:system.menu.devel');

    // The Devel toolbar implementation should not interfere with the page
    // cacheability, so you expect a MISS value in the X-Drupal-Dynamic-Cache
    // header the first time.
    $this->assertSession()->responseHeaderContains('X-Drupal-Dynamic-Cache', 'MISS');

    // Triggers a page reload and verify that the page is served from the
    // cache.
    $this->drupalGet('');
    $this->assertSession()->responseHeaderContains('X-Drupal-Dynamic-Cache', 'HIT');
  }

  /**
   * Tests toolbar integration.
   */
  public function testToolbarIntegration() {
    $library_css_url = 'devel/css/devel.toolbar.css';
    $toolbar_selector = '#toolbar-bar .toolbar-tab';
    $toolbar_tab_selector = '#toolbar-bar .toolbar-tab a.toolbar-icon-devel';
    $toolbar_tray_selector = '#toolbar-bar .toolbar-tab #toolbar-item-devel-tray';

    // Ensures that devel toolbar item is accessible only for user with the
    // adequate permissions.
    $this->drupalGet('');
    $this->assertSession()->responseNotContains($library_css_url);
    $this->assertSession()->elementNotExists('css', $toolbar_selector);
    $this->assertSession()->elementNotExists('css', $toolbar_tab_selector);

    $this->drupalLogin($this->toolbarUser);
    $this->assertSession()->responseNotContains($library_css_url);
    $this->assertSession()->elementExists('css', $toolbar_selector);
    $this->assertSession()->elementNotExists('css', $toolbar_tab_selector);

    $this->drupalLogin($this->develUser);
    $this->assertSession()->responseContains($library_css_url);
    $this->assertSession()->elementExists('css', $toolbar_selector);
    $this->assertSession()->elementExists('css', $toolbar_tab_selector);
    $this->assertSession()->elementTextContains('css', $toolbar_tab_selector, 'Devel');

    // Ensures that the configure link in the toolbar is present and point to
    // the correct page.
    $this->clickLink('Configure');
    $this->assertSession()->addressEquals('admin/config/development/devel/toolbar');

    // Ensures that the toolbar tray contains the all the menu links. To the
    // links not marked as always visible will be assigned a css class that
    // allow to hide they when the toolbar has horizontal orientation.
    $this->drupalGet('');
    $toolbar_tray = $this->assertSession()->elementExists('css', $toolbar_tray_selector);

    $devel_menu_items = $this->getMenuLinkInfos();
    $toolbar_items = $toolbar_tray->findAll('css', 'ul.toolbar-menu a');
    $this->assertCount(count($devel_menu_items), $toolbar_items);

    foreach ($devel_menu_items as $link) {
      $item_selector = sprintf('ul.toolbar-menu a:contains("%s")', $link['title']);
      $item = $this->assertSession()->elementExists('css', $item_selector, $toolbar_tray);
      // TODO: find a more correct way to test link url.
      $this->assertContains(strtok($link['url'], '?'), $item->getAttribute('href'));

      $not_visible = !in_array($link['id'], $this->defaultToolbarItems);
      $this->assertTrue($not_visible === $item->hasClass('toolbar-horizontal-item-hidden'));
    }

    // Ensures that changing the toolbar settings configuration the changes are
    // immediately visible.
    $saved_items = $this->config('devel.toolbar.settings')->get('toolbar_items');
    $saved_items[] = 'devel.event_info';

    $this->config('devel.toolbar.settings')
      ->set('toolbar_items', $saved_items)
      ->save();

    $this->drupalGet('');
    $toolbar_tray = $this->assertSession()->elementExists('css', $toolbar_tray_selector);
    $item = $this->assertSession()->elementExists('css', sprintf('ul.toolbar-menu a:contains("%s")', 'Events Info'), $toolbar_tray);
    $this->assertFalse($item->hasClass('toolbar-horizontal-item-hidden'));

    // Ensures that disabling a menu link it will not more shown in the toolbar
    // and that the changes are immediately visible.
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    $menu_link_manager->updateDefinition('devel.event_info', ['enabled' => FALSE]);

    $this->drupalGet('');
    $toolbar_tray = $this->assertSession()->elementExists('css', $toolbar_tray_selector);
    $this->assertSession()->elementNotExists('css', sprintf('ul.toolbar-menu a:contains("%s")', 'Events Info'), $toolbar_tray);
  }

  /**
   * Tests devel when toolbar module is not installed.
   */
  public function testToolbarModuleNotInstalled() {
    // Ensures that when toolbar module is not installed all works properly.
    \Drupal::service('module_installer')->uninstall(['toolbar']);

    $this->drupalLogin($this->develUser);

    // Toolbar settings page should respond with 404.
    $this->drupalGet('admin/config/development/devel/toolbar');
    $this->assertSession()->statusCodeEquals(404);

    // Primary local task should not contains toolbar tab.
    $this->drupalGet('admin/config/development/devel');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', '.tabs .primary a:contains("Toolbar Settings")');

    // Toolbar setting config and devel menu cache tags sholud not present.
    $this->drupalGet('');
    $this->assertSession()->responseHeaderNotContains('X-Drupal-Cache-Tags', 'config:devel.toolbar.settings');
    $this->assertSession()->responseHeaderNotContains('X-Drupal-Cache-Tags', 'config:system.menu.devel');
  }

  /**
   * Helper function for retrieve the menu link informations.
   *
   * @return array
   *   An array containing the menu link informations.
   */
  protected function getMenuLinkInfos() {
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks()->setTopLevelOnly();
    $tree = \Drupal::menuTree()->load('devel', $parameters);

    $links = [];
    foreach ($tree as $element) {
      $links[] = [
        'id' => $element->link->getPluginId(),
        'title' => $element->link->getTitle(),
        'url' => $element->link->getUrlObject()->toString(),
      ];
    }
    return $links;
  }

}
