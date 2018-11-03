<?php

namespace Drupal\KernelTests\Core\Field;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test field item methods.
 *
 * @group Field
 */
class FieldItemTest extends EntityKernelTestBase {

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->container->get('state')->set('entity_test.field_test_item', TRUE);
    $this->entityManager->clearCachedDefinitions();

    $entity_type_id = 'entity_test_mulrev';
    $this->installEntitySchema($entity_type_id);

    $this->fieldName = mb_strtolower($this->randomMachineName());

    /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'field_test',
      'entity_type' => $entity_type_id,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'entity_type' => $entity_type_id,
      'field_name' => $this->fieldName,
      'bundle' => $entity_type_id,
      'label' => 'Test field',
    ])->save();

    $this->entityManager->clearCachedDefinitions();
    $definitions = $this->entityManager->getFieldStorageDefinitions($entity_type_id);
    $this->assertTrue(!empty($definitions[$this->fieldName]));
  }

  /**
   * Tests the field item save workflow.
   */
  public function testSaveWorkflow() {
    $entity = EntityTestMulRev::create([
      'name' => $this->randomString(),
      'field_test_item' => $this->randomString(),
      $this->fieldName => $this->randomString(),
    ]);

    // Save a new entity and verify that the initial field value is overwritten
    // with a value containing the entity id, which implies a resave. Check that
    // the entity data structure and the stored values match.
    $this->assertSavedFieldItemValue($entity, "field_test:{$this->fieldName}:1:1");

    // Update the entity and verify that the field value is overwritten on
    // presave if it is not resaved.
    $this->assertSavedFieldItemValue($entity, 'overwritten');

    // Flag the field value as needing to be resaved and verify it actually is.
    $entity->field_test_item->value = $entity->{$this->fieldName}->value = 'resave';
    $this->assertSavedFieldItemValue($entity, "field_test:{$this->fieldName}:1:3");
  }

  /**
   * Checks that the saved field item value matches the expected one.
   *
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   *   The test entity.
   * @param $expected_value
   *   The expected field item value.
   *
   * @return bool
   *   TRUE if the item value matches expectations, FALSE otherwise.
   */
  protected function assertSavedFieldItemValue(EntityTest $entity, $expected_value) {
    $entity->setNewRevision(TRUE);
    $entity->save();
    $base_field_expected_value = str_replace($this->fieldName, 'field_test_item', $expected_value);
    $result = $this->assertEqual($entity->field_test_item->value, $base_field_expected_value);
    $result = $result && $this->assertEqual($entity->{$this->fieldName}->value, $expected_value);
    $entity = $this->reloadEntity($entity);
    $result = $result && $this->assertEqual($entity->field_test_item->value, $base_field_expected_value);
    $result = $result && $this->assertEqual($entity->{$this->fieldName}->value, $expected_value);
    return $result;
  }

}
