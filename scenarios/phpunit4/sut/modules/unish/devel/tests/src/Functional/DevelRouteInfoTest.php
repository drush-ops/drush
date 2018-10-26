<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests routes info pages and links.
 *
 * @group devel
 */
class DevelRouteInfoTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel', 'devel_test', 'block'];

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
    parent::setUp();

    $this->drupalPlaceBlock('system_menu_block:devel');
    $this->drupalPlaceBlock('page_title_block');

    $this->develUser = $this->drupalCreateUser(['access devel information']);
    $this->drupalLogin($this->develUser);
  }

  /**
   * Tests routes info.
   */
  public function testRouteList() {
    // Ensures that the routes info link is present on the devel menu and that
    // it points to the correct page.
    $this->drupalGet('');
    $this->clickLink('Routes Info');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/devel/routes');
    $this->assertSession()->pageTextContains('Routes');

    $page = $this->getSession()->getPage();

    // Ensures that the expected table headers are found.
    /** @var $headers \Behat\Mink\Element\NodeElement[] */
    $headers = $page->findAll('css', 'table.devel-route-list thead th');
    $this->assertEquals(4, count($headers));

    $expected_items = ['Route Name', 'Path', 'Allowed Methods', 'Operations'];
    foreach ($headers as $key => $element) {
      $this->assertSame($element->getText(), $expected_items[$key]);
    }

    // Ensures that all the routes are listed in the table.
    $routes = \Drupal::service('router.route_provider')->getAllRoutes();
    $rows = $page->findAll('css', 'table.devel-route-list tbody tr');
    $this->assertEquals(count($routes), count($rows));

    // Tests the presence of some (arbitrarily chosen) routes in the table.
    $expected_routes = [
      '<current>' => [
        'path' => '/<current>',
        'methods' => ['GET', 'POST'],
        'dynamic' => FALSE,
      ],
      'user.login' => [
        'path' => '/user/login',
        'methods' => ['GET', 'POST'],
        'dynamic' => FALSE,
      ],
      'entity.user.canonical' => [
        'path' => '/user/{user}',
        'methods' => ['GET', 'POST'],
        'dynamic' => TRUE,
      ],
      'entity.user.devel_load' => [
        'path' => '/devel/user/{user}',
        'methods' => ['ANY'],
        'dynamic' => TRUE,
      ],
    ];

    foreach ($expected_routes as $route_name => $expected) {
      $row = $page->find('css', sprintf('table.devel-route-list tbody tr:contains("%s")', $route_name));
      $this->assertNotNull($row);

      /** @var $cells \Behat\Mink\Element\NodeElement[] */
      $cells = $row->findAll('css', 'td');
      $this->assertEquals(4, count($cells));

      $cell_route_name = $cells[0];
      $this->assertEquals($route_name, $cell_route_name->getText());
      $this->assertTrue($cell_route_name->hasClass('table-filter-text-source'));

      $cell_path = $cells[1];
      $this->assertEquals($expected['path'], $cell_path->getText());
      $this->assertTrue($cell_path->hasClass('table-filter-text-source'));

      $cell_methods = $cells[2];
      $this->assertEquals(implode('', $expected['methods']), $cell_methods->getText());

      $cell_operations = $cells[3];
      $actual_href = $cell_operations->findLink('Devel')->getAttribute('href');
      if ($expected['dynamic']) {
        $parameters = ['query' => ['route_name' => $route_name]];
      }
      else {
        $parameters = ['query' => ['path' => $expected['path']]];
      }
      $expected_href = Url::fromRoute('devel.route_info.item', [], $parameters)->toString();
      $this->assertEquals($expected_href, $actual_href);
    }

    // Ensures that the page is accessible only to the users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet('devel/routes');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests route detail page.
   */
  public function testRouteDetail() {
    $expected_title = 'Route detail';
    $xpath_warning_messages = '//div[contains(@class, "messages--warning")]';

    // Ensures that devel route detail link in the menu works properly.
    $url = $this->develUser->toUrl();
    $path = '/' . $url->getInternalPath();

    $this->drupalGet($url);
    $this->clickLink('Current route info');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($expected_title);
    $expected_url = Url::fromRoute('devel.route_info.item', [], ['query' => ['path' => $path]]);
    $this->assertSession()->addressEquals($expected_url);
    $this->assertSession()->elementNotExists('xpath', $xpath_warning_messages);

    // Ensures that devel route detail works properly even when dynamic cache
    // is enabled.
    $url = Url::fromRoute('devel.simple_page');
    $path = '/' . $url->getInternalPath();

    $this->drupalGet($url);
    $this->clickLink('Current route info');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($expected_title);
    $expected_url = Url::fromRoute('devel.route_info.item', [], ['query' => ['path' => $path]]);
    $this->assertSession()->addressEquals($expected_url);
    $this->assertSession()->elementNotExists('xpath', $xpath_warning_messages);

    // Ensures that if a non existent path is passed as input, a warning
    // message is shown.
    $this->drupalGet('devel/routes/item', ['query' => ['path' => '/undefined']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($expected_title);
    $this->assertSession()->elementExists('xpath', $xpath_warning_messages);

    // Ensures that the route detail page works properly when a valid route
    // name input is passed.
    $this->drupalGet('devel/routes/item', ['query' => ['route_name' => 'devel.simple_page']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($expected_title);
    $this->assertSession()->elementNotExists('xpath', $xpath_warning_messages);

    // Ensures that if a non existent route name is passed as input a warning
    // message is shown.
    $this->drupalGet('devel/routes/item', ['query' => ['route_name' => 'not.exists']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($expected_title);
    $this->assertSession()->elementExists('xpath', $xpath_warning_messages);

    // Ensures that if no 'path' nor 'name' query string is passed as input,
    // devel route detail page does not return errors.
    $this->drupalGet('devel/routes/item');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($expected_title);

    // Ensures that the page is accessible ony to the users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet('devel/routes/item');
    $this->assertSession()->statusCodeEquals(403);
  }

}
