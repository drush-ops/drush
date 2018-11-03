<?php

namespace Drupal\Tests\options\Kernel;

use Drupal\Core\Entity\Exception\FieldStorageDefinitionUpdateForbiddenException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests for the 'Options' field types.
 *
 * @group options
 */
class OptionsFieldTest extends OptionsFieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['options'];

  /**
   * Test that allowed values can be updated.
   */
  public function testUpdateAllowedValues() {
    // All three options appear.
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][1]), 'Option 1 exists');
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][2]), 'Option 2 exists');
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][3]), 'Option 3 exists');

    // Use one of the values in an actual entity, and check that this value
    // cannot be removed from the list.
    $entity = EntityTest::create();
    $entity->{$this->fieldName}->value = 1;
    $entity->save();
    $this->fieldStorage->setSetting('allowed_values', [2 => 'Two']);
    try {
      $this->fieldStorage->save();
      $this->fail(t('Cannot update a list field storage to not include keys with existing data.'));
    }
    catch (FieldStorageDefinitionUpdateForbiddenException $e) {
      $this->pass(t('Cannot update a list field storage to not include keys with existing data.'));
    }
    // Empty the value, so that we can actually remove the option.
    unset($entity->{$this->fieldName});
    $entity->save();

    // Removed options do not appear.
    $this->fieldStorage->setSetting('allowed_values', [2 => 'Two']);
    $this->fieldStorage->save();
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertTrue(empty($form[$this->fieldName]['widget'][1]), 'Option 1 does not exist');
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][2]), 'Option 2 exists');
    $this->assertTrue(empty($form[$this->fieldName]['widget'][3]), 'Option 3 does not exist');

    // Completely new options appear.
    $this->fieldStorage->setSetting('allowed_values', [10 => 'Update', 20 => 'Twenty']);
    $this->fieldStorage->save();
    // The entity holds an outdated field object with the old allowed values
    // setting, so we need to reinitialize the entity object.
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertTrue(empty($form[$this->fieldName]['widget'][1]), 'Option 1 does not exist');
    $this->assertTrue(empty($form[$this->fieldName]['widget'][2]), 'Option 2 does not exist');
    $this->assertTrue(empty($form[$this->fieldName]['widget'][3]), 'Option 3 does not exist');
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][10]), 'Option 10 exists');
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][20]), 'Option 20 exists');

    // Options are reset when a new field with the same name is created.
    $this->fieldStorage->delete();
    FieldStorageConfig::create($this->fieldStorageDefinition)->save();
    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'required' => TRUE,
    ])->save();
    entity_get_form_display('entity_test', 'entity_test', 'default')
      ->setComponent($this->fieldName, [
        'type' => 'options_buttons',
      ])
      ->save();
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][1]), 'Option 1 exists');
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][2]), 'Option 2 exists');
    $this->assertTrue(!empty($form[$this->fieldName]['widget'][3]), 'Option 3 exists');

    // Test the generateSampleValue() method.
    $entity = EntityTest::create();
    $entity->{$this->fieldName}->generateSampleItems();
    $this->entityValidateAndSave($entity);
  }

}
