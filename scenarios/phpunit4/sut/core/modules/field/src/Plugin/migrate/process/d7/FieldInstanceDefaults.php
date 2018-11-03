<?php

namespace Drupal\field\Plugin\migrate\process\d7;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "d7_field_instance_defaults"
 * )
 */
class FieldInstanceDefaults extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    list($default_value, $widget_settings) = $value;
    $widget_type = $widget_settings['type'];
    $default_value = $default_value ?: [];

    // In Drupal 7, the default value for email fields is stored in the key
    // 'email' while in Drupal 8 it is stored in the key 'value'.
    if ($widget_type == 'email_textfield' && $default_value) {
      $default_value[0]['value'] = $default_value[0]['email'];
      unset($default_value[0]['email']);
    }

    return $default_value;
  }

}
