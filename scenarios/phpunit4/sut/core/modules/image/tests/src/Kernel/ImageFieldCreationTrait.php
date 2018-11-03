<?php

namespace Drupal\Tests\image\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides a helper method for creating Image fields.
 */
trait ImageFieldCreationTrait {

  /**
   * Create a new image field.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $type_name
   *   The node type that this field will be added to.
   * @param array $storage_settings
   *   (optional) A list of field storage settings that will be added to the
   *   defaults.
   * @param array $field_settings
   *   (optional) A list of instance settings that will be added to the instance
   *   defaults.
   * @param array $widget_settings
   *   (optional) Widget settings to be added to the widget defaults.
   * @param array $formatter_settings
   *   (optional) Formatter settings to be added to the formatter defaults.
   * @param string $description
   *   (optional) A description for the field. Defaults to ''.
   */
  protected function createImageField($name, $type_name, $storage_settings = [], $field_settings = [], $widget_settings = [], $formatter_settings = [], $description = '') {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'image',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => $name,
      'label' => $name,
      'entity_type' => 'node',
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
      'description' => $description,
    ]);
    $field_config->save();

    entity_get_form_display('node', $type_name, 'default')
      ->setComponent($name, [
        'type' => 'image_image',
        'settings' => $widget_settings,
      ])
      ->save();

    entity_get_display('node', $type_name, 'default')
      ->setComponent($name, [
        'type' => 'image',
        'settings' => $formatter_settings,
      ])
      ->save();

    return $field_config;
  }

}
