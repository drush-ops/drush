<?php

namespace Drupal\Tests\system\Functional\Entity;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests EntityViewController functionality.
 *
 * @group Entity
 */
class EntityViewControllerTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['entity_test'];

  /**
   * Array of test entities.
   *
   * @var array
   */
  protected $entities = [];

  protected function setUp() {
    parent::setUp();
    // Create some dummy entity_test entities.
    for ($i = 0; $i < 2; $i++) {
      $entity_test = $this->createTestEntity('entity_test');
      $entity_test->save();
      $this->entities[] = $entity_test;
    }

    $this->drupalLogin($this->drupalCreateUser(['view test entity']));
  }

  /**
   * Tests EntityViewController.
   */
  public function testEntityViewController() {
    $get_label_markup = function ($label) {
      return '<h1 class="page-title">
            <div class="field field--name-name field--type-string field--label-hidden field__item">' . $label . '</div>
      </h1>';
    };

    foreach ($this->entities as $entity) {
      $this->drupalGet('entity_test/' . $entity->id());
      $this->assertRaw($entity->label());
      $this->assertRaw($get_label_markup($entity->label()));
      $this->assertRaw('full');

      $this->drupalGet('entity_test_converter/' . $entity->id());
      $this->assertRaw($entity->label());
      $this->assertRaw('full');

      $this->drupalGet('entity_test_no_view_mode/' . $entity->id());
      $this->assertRaw($entity->label());
      $this->assertRaw('full');
    }

    // Test viewing a revisionable entity.
    $entity_test_rev = $this->createTestEntity('entity_test_rev');
    $entity_test_rev->save();
    $entity_test_rev->name->value = 'rev 2';
    $entity_test_rev->setNewRevision(TRUE);
    $entity_test_rev->isDefaultRevision(TRUE);
    $entity_test_rev->save();
    $this->drupalGet('entity_test_rev/' . $entity_test_rev->id() . '/revision/' . $entity_test_rev->revision_id->value . '/view');
    $this->assertRaw($entity_test_rev->label());
    $this->assertRaw($get_label_markup($entity_test_rev->label()));

    // As entity_test IDs must be integers, make sure requests for non-integer
    // IDs return a page not found error.
    $this->drupalGet('entity_test/invalid');
    $this->assertResponse(404);
  }

  /**
   * Tests field item attributes.
   */
  public function testFieldItemAttributes() {
    // Make sure the test field will be rendered.
    entity_get_display('entity_test', 'entity_test', 'default')
      ->setComponent('field_test_text', ['type' => 'text_default'])
      ->save();

    // Create an entity and save test value in field_test_text.
    $test_value = $this->randomMachineName();
    $entity = EntityTest::create();
    $entity->field_test_text = $test_value;
    $entity->save();

    // Browse to the entity and verify that the attribute is rendered in the
    // field item HTML markup.
    $this->drupalGet('entity_test/' . $entity->id());
    $xpath = $this->xpath('//div[@data-field-item-attr="foobar"]/p[text()=:value]', [':value' => $test_value]);
    $this->assertTrue($xpath, 'The field item attribute has been found in the rendered output of the field.');

    // Enable the RDF module to ensure that two modules can add attributes to
    // the same field item.
    \Drupal::service('module_installer')->install(['rdf']);
    $this->resetAll();

    // Set an RDF mapping for the field_test_text field. This RDF mapping will
    // be turned into RDFa attributes in the field item output.
    $mapping = rdf_get_mapping('entity_test', 'entity_test');
    $mapping->setFieldMapping('field_test_text', [
      'properties' => ['schema:text'],
    ])->save();
    // Browse to the entity and verify that the attributes from both modules
    // are rendered in the field item HTML markup.
    $this->drupalGet('entity_test/' . $entity->id());
    $xpath = $this->xpath('//div[@data-field-item-attr="foobar" and @property="schema:text"]/p[text()=:value]', [':value' => $test_value]);
    $this->assertTrue($xpath, 'The field item attributes from both modules have been found in the rendered output of the field.');
  }

  /**
   * Tests that a view builder can successfully override the view builder.
   */
  public function testEntityViewControllerViewBuilder() {
    $entity_test = $this->createTestEntity('entity_test_view_builder');
    $entity_test->save();
    $this->drupalGet('entity_test_view_builder/' . $entity_test->id());
    $this->assertText($entity_test->label());
  }

  /**
   * Creates an entity for testing.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created entity.
   */
  protected function createTestEntity($entity_type) {
    $data = [
      'bundle' => $entity_type,
      'name' => $this->randomMachineName(),
    ];
    return $this->container->get('entity.manager')->getStorage($entity_type)->create($data);
  }

}
