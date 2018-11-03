<?php

namespace Drupal\Tests\text\Kernel;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests using entity fields of the text summary field type.
 *
 * @group text
 */
class TextWithSummaryItemTest extends FieldKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['filter'];

  /**
   * Field storage entity.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * Field entity.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');

    // Create the necessary formats.
    $this->installConfig(['filter']);
    FilterFormat::create([
      'format' => 'no_filters',
      'filters' => [],
    ])->save();
  }

  /**
   * Tests processed properties.
   */
  public function testCrudAndUpdate() {
    $entity_type = 'entity_test';
    $this->createField($entity_type);

    // Create an entity with a summary and no text format.
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entity_type);
    $entity = $storage->create();
    $entity->summary_field->value = $value = $this->randomMachineName();
    $entity->summary_field->summary = $summary = $this->randomMachineName();
    $entity->summary_field->format = NULL;
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $entity = $storage->load($entity->id());
    $this->assertTrue($entity->summary_field instanceof FieldItemListInterface, 'Field implements interface.');
    $this->assertTrue($entity->summary_field[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->summary_field->value, $value);
    $this->assertEqual($entity->summary_field->summary, $summary);
    $this->assertNull($entity->summary_field->format);
    // Even if no format is given, if text processing is enabled, the default
    // format is used.
    $this->assertEqual($entity->summary_field->processed, "<p>$value</p>\n");
    $this->assertEqual($entity->summary_field->summary_processed, "<p>$summary</p>\n");

    // Change the format, this should update the processed properties.
    $entity->summary_field->format = 'no_filters';
    $this->assertEqual($entity->summary_field->processed, $value);
    $this->assertEqual($entity->summary_field->summary_processed, $summary);

    // Test the generateSampleValue() method.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create();
    $entity->summary_field->generateSampleItems();
    $this->entityValidateAndSave($entity);
  }

  /**
   * Creates a text_with_summary field storage and field.
   *
   * @param string $entity_type
   *   Entity type for which the field should be created.
   */
  protected function createField($entity_type) {
    // Create a field .
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => 'summary_field',
      'entity_type' => $entity_type,
      'type' => 'text_with_summary',
      'settings' => [
        'max_length' => 10,
      ],
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => $entity_type,
    ]);
    $this->field->save();
  }

}
