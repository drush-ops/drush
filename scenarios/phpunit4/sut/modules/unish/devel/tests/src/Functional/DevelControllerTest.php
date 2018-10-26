<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Devel controller.
 *
 * @group devel
 */
class DevelControllerTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel', 'node', 'entity_test', 'devel_entity_test', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a test entity.
    $random_label = $this->randomMachineName();
    $data = ['type' => 'entity_test', 'name' => $random_label];
    $this->entity = entity_create('entity_test', $data);
    $this->entity->save();

    // Create a test entity with only canonical route.
    $random_label = $this->randomMachineName();
    $data = ['type' => 'devel_entity_test_canonical', 'name' => $random_label];
    $this->entity_canonical = entity_create('devel_entity_test_canonical', $data);
    $this->entity_canonical->save();

    // Create a test entity with only edit route.
    $random_label = $this->randomMachineName();
    $data = ['type' => 'devel_entity_test_edit', 'name' => $random_label];
    $this->entity_edit = entity_create('devel_entity_test_edit', $data);
    $this->entity_edit->save();

    // Create a test entity with no routes.
    $random_label = $this->randomMachineName();
    $data = ['type' => 'devel_entity_test_no_links', 'name' => $random_label];
    $this->entity_no_links = entity_create('devel_entity_test_no_links', $data);
    $this->entity_no_links->save();

    $this->drupalPlaceBlock('local_tasks_block');

    $web_user = $this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'access devel information',
    ]);
    $this->drupalLogin($web_user);
  }

  function testRouteGeneration() {
    // Test Devel load and render routes for entities with both route
    // definitions.
    $this->drupalGet('entity_test/' . $this->entity->id());
    $this->assertText('Devel', 'Devel tab is present');
    $this->drupalGet('devel/entity_test/' . $this->entity->id());
    $this->assertResponse(200);
    $this->assertText('Definition', 'Devel definition tab is present');
    $this->assertText('Load', 'Devel load tab is present');
    $this->assertText('Render', 'Devel load tab is present');
    $this->assertLinkByHref('devel/entity_test/' . $this->entity->id() . '/render');
    $this->drupalGet('devel/entity_test/' . $this->entity->id() . '/render');
    $this->assertResponse(200);
    $this->assertLinkByHref('devel/entity_test/' . $this->entity->id() . '/definition');
    $this->drupalGet('devel/entity_test/' . $this->entity->id() . '/definition');
    $this->assertResponse(200);

    // Test Devel load and render routes for entities with only canonical route
    // definitions.
    $this->drupalGet('devel_entity_test_canonical/' . $this->entity_canonical->id());
    $this->assertText('Devel', 'Devel tab is present');
    //TODO this fail since assertNoLinkByHref search by partial value.
    //$this->assertNoLinkByHref('devel/devel_entity_test_canonical/' . $this->entity_canonical->id());
    $this->assertLinkByHref('devel/devel_entity_test_canonical/' . $this->entity_canonical->id() . '/render');
    $this->drupalGet('devel/devel_entity_test_canonical/' . $this->entity_canonical->id());
    $this->assertResponse(404);
    $this->drupalGet('devel/devel_entity_test_canonical/' . $this->entity_canonical->id() . '/render');
    $this->assertResponse(200);
    $this->assertLinkByHref('devel/devel_entity_test_canonical/' . $this->entity_canonical->id() . '/definition');
    $this->drupalGet('devel/devel_entity_test_canonical/' . $this->entity_canonical->id() . '/definition');
    $this->assertResponse(200);

    // Test Devel load and render routes for entities with only edit route
    // definitions.
    $this->drupalGet('devel_entity_test_edit/manage/' . $this->entity_edit->id());
    $this->assertText('Devel', 'Devel tab is present');
    $this->assertLinkByHref('devel/devel_entity_test_edit/' . $this->entity_edit->id());
    $this->assertNoLinkByHref('devel/devel_entity_test_edit/' . $this->entity_edit->id() . '/render');
    $this->assertNoLinkByHref('devel/devel_entity_test_edit/' . $this->entity_edit->id() . '/definition');
    $this->drupalGet('devel/devel_entity_test_edit/' . $this->entity_edit->id());
    $this->assertResponse(200);
    $this->drupalGet('devel/devel_entity_test_edit/' . $this->entity_edit->id() . '/render');
    $this->assertResponse(404);
    $this->drupalGet('devel/devel_entity_test_edit/' . $this->entity_edit->id() . '/definition');
    $this->assertResponse(200);

    // Test Devel load and render routes for entities with no route
    // definitions.
    $this->drupalGet('devel_entity_test_no_links/' . $this->entity_edit->id());
    $this->assertNoText('Devel', 'Devel tab is not present');
    $this->assertNoLinkByHref('devel/devel_entity_test_no_links/' . $this->entity_no_links->id());
    $this->assertNoLinkByHref('devel/devel_entity_test_no_links/' . $this->entity_no_links->id() . '/render');
    $this->assertNoLinkByHref('devel/devel_entity_test_no_links/' . $this->entity_no_links->id() . '/definition');
    $this->drupalGet('devel/devel_entity_test_no_links/' . $this->entity_no_links->id());
    $this->assertResponse(404);
    $this->drupalGet('devel/devel_entity_test_no_links/' . $this->entity_no_links->id() . '/render');
    $this->assertResponse(404);
    $this->drupalGet('devel/devel_entity_test_no_links/' . $this->entity_no_links->id() . '/definition');
    $this->assertResponse(404);
  }

}
