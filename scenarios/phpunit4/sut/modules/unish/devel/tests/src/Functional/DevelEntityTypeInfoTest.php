<?php

namespace Drupal\Tests\devel\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests entity type info pages and links.
 *
 * @group devel
 */
class DevelEntityTypeInfoTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel', 'block'];

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
   * Tests entity info menu link.
   */
  public function testEntityInfoMenuLink() {
    $this->drupalPlaceBlock('system_menu_block:devel');
    // Ensures that the entity type info link is present on the devel menu and that
    // it points to the correct page.
    $this->drupalGet('');
    $this->clickLink('Entity Info');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/devel/entity/info');
    $this->assertSession()->pageTextContains('Entity Info');
  }

  /**
   * Tests entity type list page.
   */
  public function testEntityTypeList() {
    $this->drupalGet('/devel/entity/info');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Entity Info');

    $page = $this->getSession()->getPage();

    // Ensures that the entity type list table is found.
    $table = $page->find('css', 'table.devel-entity-type-list');
    $this->assertNotNull($table);

    // Ensures that the expected table headers are found.
    $headers = $table->findAll('css', 'thead th');
    $this->assertEquals(5, count($headers));

    $expected_headers = ['ID', 'Name', 'Provider', 'Class', 'Operations'];
    $actual_headers = array_map(function (NodeElement $element) {
      return $element->getText();
    }, $headers);
    $this->assertSame($expected_headers, $actual_headers);

    // Tests the presence of some (arbitrarily chosen) entity types in the table.
    $expected_types = [
      'date_format' => [
        'name' => 'Date format',
        'class' => 'Drupal\Core\Datetime\Entity\DateFormat',
        'provider' => 'core',
      ],
      'block' => [
        'name' => 'Block',
        'class' => 'Drupal\block\Entity\Block',
        'provider' => 'block',
      ],
      'entity_view_mode' => [
        'name' => 'View mode',
        'class' => 'Drupal\Core\Entity\Entity\EntityViewMode',
        'provider' => 'core',
      ],
    ];

    foreach ($expected_types as $entity_type_id => $entity_type) {
      $row = $table->find('css', sprintf('tbody tr:contains("%s")', $entity_type_id));
      $this->assertNotNull($row);

      /** @var $cells \Behat\Mink\Element\NodeElement[] */
      $cells = $row->findAll('css', 'td');
      $this->assertEquals(5, count($cells));

      $cell = $cells[0];
      $this->assertEquals($entity_type_id, $cell->getText());
      $this->assertTrue($cell->hasClass('table-filter-text-source'));

      $cell = $cells[1];
      $this->assertEquals($entity_type['name'], $cell->getText());
      $this->assertTrue($cell->hasClass('table-filter-text-source'));

      $cell = $cells[2];
      $this->assertEquals($entity_type['provider'], $cell->getText());
      $this->assertTrue($cell->hasClass('table-filter-text-source'));

      $cell = $cells[3];
      $this->assertEquals($entity_type['class'], $cell->getText());
      $this->assertTrue($cell->hasClass('table-filter-text-source'));

      $cell = $cells[4];
      $actual_href = $cell->findLink('Devel')->getAttribute('href');
      $expected_href = Url::fromRoute('devel.entity_info_page.detail', ['entity_type_id' => $entity_type_id])->toString();
      $this->assertEquals($expected_href, $actual_href);
    }

    // Ensures that the page is accessible only to the users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet('devel/entity/info');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests entity type detail page.
   */
  public function testEntityTypeDetail() {
    $entity_type_id = 'date_format';

    // Ensures that the page works as expected.
    $this->drupalGet("/devel/entity/info/$entity_type_id");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains("Entity type $entity_type_id");

    // Ensures that the page returns a 404 error if the requested entity type is
    // not defined.
    $this->drupalGet('/devel/entity/info/not_exists');
    $this->assertSession()->statusCodeEquals(404);

    // Ensures that the page is accessible ony to users with the adequate
    // permissions.
    $this->drupalLogout();
    $this->drupalGet("/devel/entity/info/$entity_type_id");
    $this->assertSession()->statusCodeEquals(403);
  }

}
