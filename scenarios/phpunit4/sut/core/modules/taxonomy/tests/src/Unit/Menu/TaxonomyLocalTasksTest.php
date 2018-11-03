<?php

namespace Drupal\Tests\taxonomy\Unit\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTestBase;

/**
 * Tests existence of taxonomy local tasks.
 *
 * @group taxonomy
 */
class TaxonomyLocalTasksTest extends LocalTaskIntegrationTestBase {

  protected function setUp() {
    $this->directoryList = ['taxonomy' => 'core/modules/taxonomy'];
    parent::setUp();
  }

  /**
   * Checks taxonomy edit local tasks.
   *
   * @dataProvider getTaxonomyPageRoutes
   */
  public function testTaxonomyPageLocalTasks($route, $subtask = []) {
    $tasks = [
      0 => ['entity.taxonomy_term.canonical', 'entity.taxonomy_term.edit_form'],
    ];
    if ($subtask) {
      $tasks[] = $subtask;
    }
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Provides a list of routes to test.
   */
  public function getTaxonomyPageRoutes() {
    return [
      ['entity.taxonomy_term.canonical'],
      ['entity.taxonomy_term.edit_form'],
    ];
  }

}
