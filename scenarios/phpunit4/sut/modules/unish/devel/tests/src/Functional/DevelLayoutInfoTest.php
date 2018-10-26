<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests layout info pages and links.
 *
 * @group devel
 */
class DevelLayoutInfoTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel', 'block', 'layout_discovery'];

  /**
   * The user for the test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $develUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    // TODO find a cleaner way to skip layout info tests when running tests on
    // Drupal branch < 8.3.x.
    if (version_compare(\Drupal::VERSION, '8.3', '<')) {
      $this->markTestSkipped('Devel Layout Info Tests only available on version 8.3.x+.');
    }

    parent::setUp();

    $this->drupalPlaceBlock('page_title_block');

    $this->develUser = $this->drupalCreateUser(['access devel information']);
    $this->drupalLogin($this->develUser);
  }

  /**
   * Tests layout info menu link.
   */
  public function testLayoutsInfoMenuLink() {
    $this->drupalPlaceBlock('system_menu_block:devel');
    // Ensures that the layout info link is present on the devel menu and that
    // it points to the correct page.
    $this->drupalGet('');
    $this->clickLink('Layouts Info');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/devel/layouts');
    $this->assertSession()->pageTextContains('Layout');
  }

  /**
   * Tests layout info page.
   */
  public function testLayoutList() {
    $this->drupalGet('/devel/layouts');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Layouts');

    $page = $this->getSession()->getPage();

    // Ensures that the layout table is found.
    $table = $page->find('css', 'table.devel-layout-list');
    $this->assertNotNull($table);

    // Ensures that the expected table headers are found.
    /** @var $headers \Behat\Mink\Element\NodeElement[] */
    $headers = $table->findAll('css', 'thead th');
    $this->assertEquals(6, count($headers));

    $expected_headers = ['Icon', 'Label', 'Description', 'Category', 'Regions', 'Provider'];
    $actual_headers = array_map(function ($element) {
      return $element->getText();
    }, $headers);
    $this->assertSame($expected_headers, $actual_headers);

    // Ensures that all the layouts are listed in the table.
    $layout_manager = \Drupal::service('plugin.manager.core.layout');
    $layouts = $layout_manager->getDefinitions();
    $table_rows = $table->findAll('css', 'tbody tr');
    $this->assertEquals(count($layouts), count($table_rows));

    $index = 0;
    foreach ($layouts as $layout) {
      $cells = $table_rows[$index]->findAll('css', 'td');
      $this->assertEquals(6, count($cells));

      $cell_layout_icon = $cells[0];
      if (empty($layout->getIconPath())) {
        // @todo test that the icon path image is set correctly
      }
      else {
        $this->assertNull($cell_layout_icon->getText());
      }

      $cell_layout_label = $cells[1];
      $this->assertEquals($cell_layout_label->getText(), $layout->getLabel());

      $cell_layout_description = $cells[2];
      $this->assertEquals($cell_layout_description->getText(), $layout->getDescription());

      $cell_layout_category = $cells[3];
      $this->assertEquals($cell_layout_category->getText(), $layout->getCategory());

      $cell_layout_regions = $cells[4];
      $this->assertEquals($cell_layout_regions->getText(), implode(', ', $layout->getRegionLabels()));

      $cell_layout_provider = $cells[5];
      $this->assertEquals($cell_layout_provider->getText(), $layout->getProvider());

      $index++;
    }

    // Ensures that the page is accessible only to the users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet('devel/layouts');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the dependency with layout_discovery module.
   */
  public function testLayoutDiscoveryDependency() {
    $this->container->get('module_installer')->uninstall(['layout_discovery']);
    $this->drupalPlaceBlock('system_menu_block:devel');

    // Ensures that the layout info link is not present on the devel menu.
    $this->drupalGet('');
    $this->assertSession()->linkNotExists('Layouts Info');

    // Ensures that the layouts info page is not available.
    $this->drupalGet('/devel/layouts');
    $this->assertSession()->statusCodeEquals(404);

    // Check a few other devel pages to verify devel module stil works.
    $this->drupalGet('/devel/events');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('devel/routes');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/devel/container/service');
    $this->assertSession()->statusCodeEquals(200);
  }

}
