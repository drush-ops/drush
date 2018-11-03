<?php

namespace Drupal\Tests\system\Functional\Menu;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\RoleInterface;

/**
 * Tests breadcrumbs functionality.
 *
 * @group Menu
 */
class BreadcrumbTest extends BrowserTestBase {

  use AssertBreadcrumbTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['menu_test', 'block'];

  /**
   * An administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A regular user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Test paths in the Standard profile.
   *
   * @var string
   */
  protected $profile = 'standard';

  protected function setUp() {
    parent::setUp();

    $perms = array_keys(\Drupal::service('user.permissions')->getPermissions());
    $this->adminUser = $this->drupalCreateUser($perms);
    $this->drupalLogin($this->adminUser);

    // This test puts menu links in the Tools menu and then tests for their
    // presence on the page, so we need to ensure that the Tools block will be
    // displayed in the admin theme.
    $this->drupalPlaceBlock('system_menu_block:tools', [
      'region' => 'content',
      'theme' => $this->config('system.theme')->get('admin'),
    ]);
  }

  /**
   * Tests breadcrumbs on node and administrative paths.
   */
  public function testBreadCrumbs() {
    // Prepare common base breadcrumb elements.
    $home = ['' => 'Home'];
    $admin = $home + ['admin' => t('Administration')];
    $config = $admin + ['admin/config' => t('Configuration')];
    $type = 'article';

    // Verify Taxonomy administration breadcrumbs.
    $trail = $admin + [
      'admin/structure' => t('Structure'),
    ];
    $this->assertBreadcrumb('admin/structure/taxonomy', $trail);

    $trail += [
      'admin/structure/taxonomy' => t('Taxonomy'),
    ];
    $this->assertBreadcrumb('admin/structure/taxonomy/manage/tags', $trail);
    $trail += [
      'admin/structure/taxonomy/manage/tags' => t('Edit Tags'),
    ];
    $this->assertBreadcrumb('admin/structure/taxonomy/manage/tags/overview', $trail);
    $this->assertBreadcrumb('admin/structure/taxonomy/manage/tags/add', $trail);

    // Verify Menu administration breadcrumbs.
    $trail = $admin + [
      'admin/structure' => t('Structure'),
    ];
    $this->assertBreadcrumb('admin/structure/menu', $trail);

    $trail += [
      'admin/structure/menu' => t('Menus'),
    ];
    $this->assertBreadcrumb('admin/structure/menu/manage/tools', $trail);

    $trail += [
      'admin/structure/menu/manage/tools' => t('Tools'),
    ];
    $this->assertBreadcrumb("admin/structure/menu/link/node.add_page/edit", $trail);
    $this->assertBreadcrumb('admin/structure/menu/manage/tools/add', $trail);

    // Verify Node administration breadcrumbs.
    $trail = $admin + [
      'admin/structure' => t('Structure'),
      'admin/structure/types' => t('Content types'),
    ];
    $this->assertBreadcrumb('admin/structure/types/add', $trail);
    $this->assertBreadcrumb("admin/structure/types/manage/$type", $trail);
    $trail += [
      "admin/structure/types/manage/$type" => t('Article'),
    ];
    $this->assertBreadcrumb("admin/structure/types/manage/$type/fields", $trail);
    $this->assertBreadcrumb("admin/structure/types/manage/$type/display", $trail);
    $trail_teaser = $trail + [
      "admin/structure/types/manage/$type/display" => t('Manage display'),
    ];
    $this->assertBreadcrumb("admin/structure/types/manage/$type/display/teaser", $trail_teaser);
    $this->assertBreadcrumb("admin/structure/types/manage/$type/delete", $trail);
    $trail += [
      "admin/structure/types/manage/$type/fields" => t('Manage fields'),
    ];
    $this->assertBreadcrumb("admin/structure/types/manage/$type/fields/node.$type.body", $trail);

    // Verify Filter text format administration breadcrumbs.
    $filter_formats = filter_formats();
    $format = reset($filter_formats);
    $format_id = $format->id();
    $trail = $config + [
      'admin/config/content' => t('Content authoring'),
    ];
    $this->assertBreadcrumb('admin/config/content/formats', $trail);

    $trail += [
      'admin/config/content/formats' => t('Text formats and editors'),
    ];
    $this->assertBreadcrumb('admin/config/content/formats/add', $trail);
    $this->assertBreadcrumb("admin/config/content/formats/manage/$format_id", $trail);
    // @todo Remove this part once we have a _title_callback, see
    //   https://www.drupal.org/node/2076085.
    $trail += [
      "admin/config/content/formats/manage/$format_id" => $format->label(),
    ];
    $this->assertBreadcrumb("admin/config/content/formats/manage/$format_id/disable", $trail);

    // Verify node breadcrumbs (without menu link).
    $node1 = $this->drupalCreateNode();
    $nid1 = $node1->id();
    $trail = $home;
    $this->assertBreadcrumb("node/$nid1", $trail);
    // Also verify that the node does not appear elsewhere (e.g., menu trees).
    $this->assertNoLink($node1->getTitle());
    // Also verify that the node does not appear elsewhere (e.g., menu trees).
    $this->assertNoLink($node1->getTitle());

    $trail += [
      "node/$nid1" => $node1->getTitle(),
    ];
    $this->assertBreadcrumb("node/$nid1/edit", $trail);

    // Verify that breadcrumb on node listing page contains "Home" only.
    $trail = [];
    $this->assertBreadcrumb('node', $trail);

    // Verify node breadcrumbs (in menu).
    // Do this separately for Main menu and Tools menu, since only the
    // latter is a preferred menu by default.
    // @todo Also test all themes? Manually testing led to the suspicion that
    //   breadcrumbs may differ, possibly due to theme overrides.
    $menus = ['main', 'tools'];
    // Alter node type menu settings.
    $node_type = NodeType::load($type);
    $node_type->setThirdPartySetting('menu_ui', 'available_menus', $menus);
    $node_type->setThirdPartySetting('menu_ui', 'parent', 'tools:');
    $node_type->save();

    foreach ($menus as $menu) {
      // Create a parent node in the current menu.
      $title = $this->randomMachineName();
      $node2 = $this->drupalCreateNode([
        'type' => $type,
        'title' => $title,
        'menu' => [
          'enabled' => 1,
          'title' => 'Parent ' . $title,
          'description' => '',
          'menu_name' => $menu,
          'parent' => '',
        ],
      ]);

      if ($menu == 'tools') {
        $parent = $node2;
      }
    }

    // Create a Tools menu link for 'node', move the last parent node menu
    // link below it, and verify a full breadcrumb for the last child node.
    $menu = 'tools';
    $edit = [
      'title[0][value]' => 'Root',
      'link[0][uri]' => '/node',
    ];
    $this->drupalPostForm("admin/structure/menu/manage/$menu/add", $edit, t('Save'));
    $menu_links = entity_load_multiple_by_properties('menu_link_content', ['title' => 'Root']);
    $link = reset($menu_links);

    $edit = [
      'menu[menu_parent]' => $link->getMenuName() . ':' . $link->getPluginId(),
    ];
    $this->drupalPostForm('node/' . $parent->id() . '/edit', $edit, t('Save'));
    $expected = [
      "node" => $link->getTitle(),
    ];
    $trail = $home + $expected;
    $tree = $expected + [
      'node/' . $parent->id() => $parent->menu['title'],
    ];
    $trail += [
      'node/' . $parent->id() => $parent->menu['title'],
    ];

    // Add a taxonomy term/tag to last node, and add a link for that term to the
    // Tools menu.
    $tags = [
      'Drupal' => [],
      'Breadcrumbs' => [],
    ];
    $edit = [
      'field_tags[target_id]' => implode(',', array_keys($tags)),
    ];
    $this->drupalPostForm('node/' . $parent->id() . '/edit', $edit, t('Save'));

    // Put both terms into a hierarchy Drupal » Breadcrumbs. Required for both
    // the menu links and the terms itself, since taxonomy_term_page() resets
    // the breadcrumb based on taxonomy term hierarchy.
    $parent_tid = 0;
    foreach ($tags as $name => $null) {
      $terms = entity_load_multiple_by_properties('taxonomy_term', ['name' => $name]);
      $term = reset($terms);
      $tags[$name]['term'] = $term;
      if ($parent_tid) {
        $edit = [
          'parent[]' => [$parent_tid],
        ];
        $this->drupalPostForm("taxonomy/term/{$term->id()}/edit", $edit, t('Save'));
      }
      $parent_tid = $term->id();
    }
    $parent_mlid = '';
    foreach ($tags as $name => $data) {
      $term = $data['term'];
      $edit = [
        'title[0][value]' => "$name link",
        'link[0][uri]' => "/taxonomy/term/{$term->id()}",
        'menu_parent' => "$menu:{$parent_mlid}",
        'enabled[value]' => 1,
      ];
      $this->drupalPostForm("admin/structure/menu/manage/$menu/add", $edit, t('Save'));
      $menu_links = entity_load_multiple_by_properties('menu_link_content', [
        'title' => $edit['title[0][value]'],
        'link.uri' => 'internal:/taxonomy/term/' . $term->id(),
      ]);
      $tags[$name]['link'] = reset($menu_links);
      $parent_mlid = $tags[$name]['link']->getPluginId();
    }

    // Verify expected breadcrumbs for menu links.
    $trail = $home;
    $tree = [];
    // Logout the user because we want to check the active class as well, which
    // is just rendered as anonymous user.
    $this->drupalLogout();
    foreach ($tags as $name => $data) {
      $term = $data['term'];
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
      $link = $data['link'];

      $link_path = $link->getUrlObject()->getInternalPath();
      $tree += [
        $link_path => $link->getTitle(),
      ];
      $this->assertBreadcrumb($link_path, $trail, $term->getName(), $tree);
      $this->assertEscaped($parent->getTitle(), 'Tagged node found.');

      // Additionally make sure that this link appears only once; i.e., the
      // untranslated menu links automatically generated from menu router items
      // ('taxonomy/term/%') should never be translated and appear in any menu
      // other than the breadcrumb trail.
      $elements = $this->xpath('//nav[@id=:menu]/descendant::a[@href=:href]', [
        ':menu' => 'block-bartik-tools',
        ':href' => Url::fromUri('base:' . $link_path)->toString(),
      ]);
      $this->assertTrue(count($elements) == 1, "Link to {$link_path} appears only once.");

      // Next iteration should expect this tag as parent link.
      // Note: Term name, not link name, due to taxonomy_term_page().
      $trail += [
        $link_path => $term->getName(),
      ];
    }

    // Verify breadcrumbs on user and user/%.
    // We need to log back in and out below, and cannot simply grant the
    // 'administer users' permission, since user_page() makes your head explode.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'access user profiles',
    ]);

    // Verify breadcrumb on front page.
    $this->assertBreadcrumb('<front>', []);

    // Verify breadcrumb on user pages (without menu link) for anonymous user.
    $trail = $home;
    $this->assertBreadcrumb('user', $trail, t('Log in'));
    $this->assertBreadcrumb('user/' . $this->adminUser->id(), $trail, $this->adminUser->getUsername());

    // Verify breadcrumb on user pages (without menu link) for registered users.
    $this->drupalLogin($this->adminUser);
    $trail = $home;
    $this->assertBreadcrumb('user', $trail, $this->adminUser->getUsername());
    $this->assertBreadcrumb('user/' . $this->adminUser->id(), $trail, $this->adminUser->getUsername());
    $trail += [
      'user/' . $this->adminUser->id() => $this->adminUser->getUsername(),
    ];
    $this->assertBreadcrumb('user/' . $this->adminUser->id() . '/edit', $trail, $this->adminUser->getUsername());

    // Create a second user to verify breadcrumb on user pages again.
    $this->webUser = $this->drupalCreateUser([
      'administer users',
      'access user profiles',
    ]);
    $this->drupalLogin($this->webUser);

    // Verify correct breadcrumb and page title on another user's account pages.
    $trail = $home;
    $this->assertBreadcrumb('user/' . $this->adminUser->id(), $trail, $this->adminUser->getUsername());
    $trail += [
      'user/' . $this->adminUser->id() => $this->adminUser->getUsername(),
    ];
    $this->assertBreadcrumb('user/' . $this->adminUser->id() . '/edit', $trail, $this->adminUser->getUsername());

    // Verify correct breadcrumb and page title when viewing own user account.
    $trail = $home;
    $this->assertBreadcrumb('user/' . $this->webUser->id(), $trail, $this->webUser->getUsername());
    $trail += [
      'user/' . $this->webUser->id() => $this->webUser->getUsername(),
    ];
    $this->assertBreadcrumb('user/' . $this->webUser->id() . '/edit', $trail, $this->webUser->getUsername());

    // Create an only slightly privileged user being able to access site reports
    // but not administration pages.
    $this->webUser = $this->drupalCreateUser([
      'access site reports',
    ]);
    $this->drupalLogin($this->webUser);

    // Verify that we can access recent log entries, there is a corresponding
    // page title, and that the breadcrumb is just the Home link (because the
    // user is not able to access "Administer".
    $trail = $home;
    $this->assertBreadcrumb('admin', $trail, t('Access denied'));
    $this->assertSession()->statusCodeEquals(403);

    // Since the 'admin' path is not accessible, we still expect only the Home
    // link.
    $this->assertBreadcrumb('admin/reports', $trail, t('Reports'));
    $this->assertSession()->statusCodeNotEquals(403);

    // Since the Reports page is accessible, that will show.
    $trail += ['admin/reports' => t('Reports')];
    $this->assertBreadcrumb('admin/reports/dblog', $trail, t('Recent log messages'));
    $this->assertSession()->statusCodeNotEquals(403);

    // Ensure that the breadcrumb is safe against XSS.
    $this->drupalGet('menu-test/breadcrumb1/breadcrumb2/breadcrumb3');
    $this->assertRaw('<script>alert(12);</script>');
    $this->assertEscaped('<script>alert(123);</script>');
  }

}
