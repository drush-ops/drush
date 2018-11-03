<?php

namespace Drupal\Tests\field\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests field elements in nested forms.
 *
 * @group field
 */
class NestedFormTest extends FieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['field_test', 'entity_test'];

  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(['view test entity', 'administer entity_test content']);
    $this->drupalLogin($web_user);

    $this->fieldStorageSingle = [
      'field_name' => 'field_single',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
    ];
    $this->fieldStorageUnlimited = [
      'field_name' => 'field_unlimited',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ];

    $this->field = [
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => $this->randomMachineName() . '_label',
      'description' => '[site:name]_description',
      'weight' => mt_rand(0, 127),
      'settings' => [
        'test_field_setting' => $this->randomMachineName(),
      ],
    ];
  }

  /**
   * Tests Field API form integration within a subform.
   */
  public function testNestedFieldForm() {
    // Add two fields on the 'entity_test'
    FieldStorageConfig::create($this->fieldStorageSingle)->save();
    FieldStorageConfig::create($this->fieldStorageUnlimited)->save();
    $this->field['field_name'] = 'field_single';
    $this->field['label'] = 'Single field';
    FieldConfig::create($this->field)->save();
    entity_get_form_display($this->field['entity_type'], $this->field['bundle'], 'default')
      ->setComponent($this->field['field_name'])
      ->save();
    $this->field['field_name'] = 'field_unlimited';
    $this->field['label'] = 'Unlimited field';
    FieldConfig::create($this->field)->save();
    entity_get_form_display($this->field['entity_type'], $this->field['bundle'], 'default')
      ->setComponent($this->field['field_name'])
      ->save();

    // Create two entities.
    $entity_type = 'entity_test';
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entity_type);

    $entity_1 = $storage->create(['id' => 1]);
    $entity_1->enforceIsNew();
    $entity_1->field_single->value = 0;
    $entity_1->field_unlimited->value = 1;
    $entity_1->save();

    $entity_2 = $storage->create(['id' => 2]);
    $entity_2->enforceIsNew();
    $entity_2->field_single->value = 10;
    $entity_2->field_unlimited->value = 11;
    $entity_2->save();

    // Display the 'combined form'.
    $this->drupalGet('test-entity/nested/1/2');
    $this->assertFieldByName('field_single[0][value]', 0, 'Entity 1: field_single value appears correctly is the form.');
    $this->assertFieldByName('field_unlimited[0][value]', 1, 'Entity 1: field_unlimited value 0 appears correctly is the form.');
    $this->assertFieldByName('entity_2[field_single][0][value]', 10, 'Entity 2: field_single value appears correctly is the form.');
    $this->assertFieldByName('entity_2[field_unlimited][0][value]', 11, 'Entity 2: field_unlimited value 0 appears correctly is the form.');

    // Submit the form and check that the entities are updated accordingly.
    $edit = [
      'field_single[0][value]' => 1,
      'field_unlimited[0][value]' => 2,
      'field_unlimited[1][value]' => 3,
      'entity_2[field_single][0][value]' => 11,
      'entity_2[field_unlimited][0][value]' => 12,
      'entity_2[field_unlimited][1][value]' => 13,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $entity_1 = $storage->load(1);
    $entity_2 = $storage->load(2);
    $this->assertFieldValues($entity_1, 'field_single', [1]);
    $this->assertFieldValues($entity_1, 'field_unlimited', [2, 3]);
    $this->assertFieldValues($entity_2, 'field_single', [11]);
    $this->assertFieldValues($entity_2, 'field_unlimited', [12, 13]);

    // Submit invalid values and check that errors are reported on the
    // correct widgets.
    $edit = [
      'field_unlimited[1][value]' => -1,
    ];
    $this->drupalPostForm('test-entity/nested/1/2', $edit, t('Save'));
    $this->assertRaw(t('%label does not accept the value -1', ['%label' => 'Unlimited field']), 'Entity 1: the field validation error was reported.');
    $error_field = $this->xpath('//input[@id=:id and contains(@class, "error")]', [':id' => 'edit-field-unlimited-1-value']);
    $this->assertTrue($error_field, 'Entity 1: the error was flagged on the correct element.');
    $edit = [
      'entity_2[field_unlimited][1][value]' => -1,
    ];
    $this->drupalPostForm('test-entity/nested/1/2', $edit, t('Save'));
    $this->assertRaw(t('%label does not accept the value -1', ['%label' => 'Unlimited field']), 'Entity 2: the field validation error was reported.');
    $error_field = $this->xpath('//input[@id=:id and contains(@class, "error")]', [':id' => 'edit-entity-2-field-unlimited-1-value']);
    $this->assertTrue($error_field, 'Entity 2: the error was flagged on the correct element.');

    // Test that reordering works on both entities.
    $edit = [
      'field_unlimited[0][_weight]' => 0,
      'field_unlimited[1][_weight]' => -1,
      'entity_2[field_unlimited][0][_weight]' => 0,
      'entity_2[field_unlimited][1][_weight]' => -1,
    ];
    $this->drupalPostForm('test-entity/nested/1/2', $edit, t('Save'));
    $this->assertFieldValues($entity_1, 'field_unlimited', [3, 2]);
    $this->assertFieldValues($entity_2, 'field_unlimited', [13, 12]);

    // Test the 'add more' buttons.
    // 'Add more' button in the first entity:
    $this->drupalGet('test-entity/nested/1/2');
    $this->drupalPostForm(NULL, [], 'field_unlimited_add_more');
    $this->assertFieldByName('field_unlimited[0][value]', 3, 'Entity 1: field_unlimited value 0 appears correctly is the form.');
    $this->assertFieldByName('field_unlimited[1][value]', 2, 'Entity 1: field_unlimited value 1 appears correctly is the form.');
    $this->assertFieldByName('field_unlimited[2][value]', '', 'Entity 1: field_unlimited value 2 appears correctly is the form.');
    $this->assertFieldByName('field_unlimited[3][value]', '', 'Entity 1: an empty widget was added for field_unlimited value 3.');
    // 'Add more' button in the first entity (changing field values):
    $edit = [
      'entity_2[field_unlimited][0][value]' => 13,
      'entity_2[field_unlimited][1][value]' => 14,
      'entity_2[field_unlimited][2][value]' => 15,
    ];
    $this->drupalPostForm(NULL, $edit, 'entity_2_field_unlimited_add_more');
    $this->assertFieldByName('entity_2[field_unlimited][0][value]', 13, 'Entity 2: field_unlimited value 0 appears correctly is the form.');
    $this->assertFieldByName('entity_2[field_unlimited][1][value]', 14, 'Entity 2: field_unlimited value 1 appears correctly is the form.');
    $this->assertFieldByName('entity_2[field_unlimited][2][value]', 15, 'Entity 2: field_unlimited value 2 appears correctly is the form.');
    $this->assertFieldByName('entity_2[field_unlimited][3][value]', '', 'Entity 2: an empty widget was added for field_unlimited value 3.');
    // Save the form and check values are saved correctly.
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertFieldValues($entity_1, 'field_unlimited', [3, 2]);
    $this->assertFieldValues($entity_2, 'field_unlimited', [13, 14, 15]);
  }

  /**
   * Tests entity level validation within subforms.
   */
  public function testNestedEntityFormEntityLevelValidation() {
    // Create two entities.
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_constraints');

    $entity_1 = $storage->create();
    $entity_1->save();

    $entity_2 = $storage->create();
    $entity_2->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Display the 'combined form'.
    $this->drupalGet("test-entity-constraints/nested/{$entity_1->id()}/{$entity_2->id()}");
    $assert_session->hiddenFieldValueEquals('entity_2[changed]', REQUEST_TIME);

    // Submit the form and check that the entities are updated accordingly.
    $assert_session->hiddenFieldExists('entity_2[changed]')
      ->setValue(REQUEST_TIME - 86400);
    $page->pressButton(t('Save'));

    $elements = $this->cssSelect('.entity-2.error');
    $this->assertEqual(1, count($elements), 'The whole nested entity form has been correctly flagged with an error class.');
  }

}
