<?php

namespace Drupal\field\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "d6_field_instance_defaults"
 * )
 */
class FieldInstanceDefaults extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Set the field instance defaults.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    list($widget_type, $widget_settings) = $value;
    $default = [];

    switch ($widget_type) {
      case 'text_textfield':
      case 'number':
      case 'phone_textfield':
        if (!empty($widget_settings['default_value'][0]['value'])) {
          $default['value'] = $widget_settings['default_value'][0]['value'];
        }
        break;

      case 'imagefield_widget':
        // @todo, load the image and populate the defaults.
        // $default['default_image'] = $widget_settings['default_image'];
        break;

      case 'date_select':
        if (!empty($widget_settings['default_value'])) {
          $default['default_date_type'] = 'relative';
          $default['default_date'] = $widget_settings['default_value'];
        }
        break;

      case 'email_textfield':
        if (!empty($widget_settings['default_value'][0]['email'])) {
          $default['value'] = $widget_settings['default_value'][0]['email'];
        }
        break;

      case 'link':
        if (!empty($widget_settings['default_value'][0]['url'])) {
          $default['title'] = $widget_settings['default_value'][0]['title'];
          $default['url'] = $widget_settings['default_value'][0]['url'];
          $default['options'] = ['attributes' => []];
        }
        break;
    }
    if (!empty($default)) {
      $default = [$default];
    }
    return $default;
  }

}
