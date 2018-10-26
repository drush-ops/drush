<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests container info pages and links.
 *
 * @group devel
 */
class DevelContainerInfoTest extends BrowserTestBase {

  use DevelWebAssertHelper;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel', 'devel_test', 'block'];

  /**
   * The user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $develUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->develUser = $this->drupalCreateUser(['access devel information']);
    $this->drupalLogin($this->develUser);
  }

  /**
   * Tests container info menu link.
   */
  public function testContainerInfoMenuLink() {
    $this->drupalPlaceBlock('system_menu_block:devel');
    // Ensures that the events info link is present on the devel menu and that
    // it points to the correct page.
    $this->drupalGet('');
    $this->clickLink('Container Info');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/devel/container/service');
    $this->assertSession()->pageTextContains('Container services');
  }

  /**
   * Tests service list page.
   */
  public function testServiceList() {
    $this->drupalGet('/devel/container/service');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Container services');
    $this->assertContainerInfoLocalTasks();

    $page = $this->getSession()->getPage();

    // Ensures that the services table is found.
    $table = $page->find('css', 'table.devel-service-list');
    $this->assertNotNull($table);

    // Ensures that the expected table headers are found.
    /** @var $headers \Behat\Mink\Element\NodeElement[] */
    $headers = $table->findAll('css', 'thead th');
    $this->assertEquals(4, count($headers));

    $expected_headers = ['ID', 'Class', 'Alias', 'Operations'];
    $actual_headers = array_map(function ($element) {
      return $element->getText();
    }, $headers);
    $this->assertSame($expected_headers, $actual_headers);

    // Ensures that all the serivices are listed in the table.
    $cached_definition = \Drupal::service('kernel')->getCachedContainerDefinition();
    $this->assertNotNull($cached_definition);
    $rows = $table->findAll('css', 'tbody tr');
    $this->assertEquals(count($cached_definition['services']), count($rows));

    // Tests the presence of some (arbitrarily chosen) services in the table.
    $expected_services = [
      'config.factory' => [
        'class' => 'Drupal\Core\Config\ConfigFactory',
        'alias' => '',
      ],
      'devel.route_subscriber' => [
        'class' => 'Drupal\devel\Routing\RouteSubscriber',
        'alias' => '',
      ],
      'plugin.manager.element_info' => [
        'class' => 'Drupal\Core\Render\ElementInfoManager',
        'alias' => 'element_info',
      ],
    ];

    foreach ($expected_services as $service_id => $expected) {
      $row = $table->find('css', sprintf('tbody tr:contains("%s")', $service_id));
      $this->assertNotNull($row);

      /** @var $cells \Behat\Mink\Element\NodeElement[] */
      $cells = $row->findAll('css', 'td');
      $this->assertEquals(4, count($cells));

      $cell_service_id = $cells[0];
      $this->assertEquals($service_id, $cell_service_id->getText());
      $this->assertTrue($cell_service_id->hasClass('table-filter-text-source'));

      $cell_class = $cells[1];
      $this->assertEquals($expected['class'], $cell_class->getText());
      $this->assertTrue($cell_class->hasClass('table-filter-text-source'));

      $cell_alias = $cells[2];
      $this->assertEquals($expected['alias'], $cell_alias->getText());
      $this->assertTrue($cell_class->hasClass('table-filter-text-source'));

      $cell_operations = $cells[3];
      $actual_href = $cell_operations->findLink('Devel')->getAttribute('href');
      $expected_href = Url::fromRoute('devel.container_info.service.detail', ['service_id' => $service_id])->toString();
      $this->assertEquals($expected_href, $actual_href);
    }

    // Ensures that the page is accessible ony to users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet('devel/container/service');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests service detail page.
   */
  public function testServiceDetail() {
    $service_id = 'devel.dumper';

    // Ensures that the page works as expected.
    $this->drupalGet("/devel/container/service/$service_id");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("Service $service_id detail");

    // Ensures that the page returns a 404 error if the requested service is
    // not defined.
    $this->drupalGet('/devel/container/service/not.exists');
    $this->assertSession()->statusCodeEquals(404);

    // Ensures that the page is accessible ony to users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet("devel/container/service/$service_id");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests parameter list page.
   */
  public function testParameterList() {
    // Ensures that the page works as expected.
    $this->drupalGet('/devel/container/parameter');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Container parameters');
    $this->assertContainerInfoLocalTasks();

    $page = $this->getSession()->getPage();

    // Ensures that the parameters table is found.
    $table = $page->find('css', 'table.devel-parameter-list');
    $this->assertNotNull($table);

    // Ensures that the expected table headers are found.
    /** @var $headers \Behat\Mink\Element\NodeElement[] */
    $headers = $table->findAll('css', 'thead th');
    $this->assertEquals(2, count($headers));

    $expected_headers = ['Name', 'Operations'];
    $actual_headers = array_map(function ($element) {
      return $element->getText();
    }, $headers);
    $this->assertSame($expected_headers, $actual_headers);

    // Ensures that all the parameters are listed in the table.
    $cached_definition = \Drupal::service('kernel')->getCachedContainerDefinition();
    $this->assertNotNull($cached_definition);
    $rows = $table->findAll('css', 'tbody tr');
    $this->assertEquals(count($cached_definition['parameters']), count($rows));

    // Tests the presence of some parameters in the table.
    $expected_parameters = [
      'container.modules',
      'cache_bins',
      'factory.keyvalue',
      'twig.config',
    ];

    foreach ($expected_parameters as $parameter_name) {
      $row = $table->find('css', sprintf('tbody tr:contains("%s")', $parameter_name));
      $this->assertNotNull($row);

      /** @var $cells \Behat\Mink\Element\NodeElement[] */
      $cells = $row->findAll('css', 'td');
      $this->assertEquals(2, count($cells));

      $cell_parameter_name = $cells[0];
      $this->assertEquals($parameter_name, $cell_parameter_name->getText());
      $this->assertTrue($cell_parameter_name->hasClass('table-filter-text-source'));

      $cell_operations = $cells[1];
      $actual_href = $cell_operations->findLink('Devel')->getAttribute('href');
      $expected_href = Url::fromRoute('devel.container_info.parameter.detail', ['parameter_name' => $parameter_name])->toString();
      $this->assertEquals($expected_href, $actual_href);
    }

    // Ensures that the page is accessible ony to users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet('devel/container/service');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests parameter detail page.
   */
  public function testParameterDetail() {
    $parameter_name = 'cache_bins';

    // Ensures that the page works as expected.
    $this->drupalGet("/devel/container/parameter/$parameter_name");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("Parameter $parameter_name value");

    // Ensures that the page returns a 404 error if the requested parameter is
    // not defined.
    $this->drupalGet('/devel/container/parameter/not_exists');
    $this->assertSession()->statusCodeEquals(404);

    // Ensures that the page is accessible ony to users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet("devel/container/service/$parameter_name");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Asserts that container info local tasks are present.
   */
  protected function assertContainerInfoLocalTasks() {
    $expected_local_tasks = [
      ['devel.container_info.service', []],
      ['devel.container_info.parameter', []],
    ];

    $this->assertLocalTasks($expected_local_tasks);
  }

}
