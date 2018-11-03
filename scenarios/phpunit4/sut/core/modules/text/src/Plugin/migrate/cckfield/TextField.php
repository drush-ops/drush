<?php

namespace Drupal\text\Plugin\migrate\cckfield;

@trigger_error('TextField is deprecated in Drupal 8.3.x and will be removed before Drupal 9.0.x. Use \Drupal\text\Plugin\migrate\field\d6\TextField or \Drupal\text\Plugin\migrate\field\d7\TextField instead.', E_USER_DEPRECATED);

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;

/**
 * @MigrateCckField(
 *   id = "text",
 *   type_map = {
 *     "text" = "text",
 *     "text_long" = "text_long",
 *     "text_with_summary" = "text_with_summary"
 *   },
 *   core = {6,7},
 *   source_module = "text",
 *   destination_module = "text",
 * )
 *
 * @deprecated in Drupal 8.3.x, to be removed before Drupal 9.0.x. Use
 * \Drupal\text\Plugin\migrate\field\d6\TextField or
 * \Drupal\text\Plugin\migrate\field\d7\TextField instead.
 *
 * @see https://www.drupal.org/node/2751897
 */
class TextField extends CckFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'text_textfield' => 'text_textfield',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'default' => 'text_default',
      'trimmed' => 'text_trimmed',
      'plain' => 'basic_string',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $field_info) {
    $widget_type = isset($field_info['widget_type']) ? $field_info['widget_type'] : $field_info['widget']['type'];

    if ($widget_type == 'optionwidgets_onoff') {
      $process = [
        'value' => [
          'plugin' => 'static_map',
          'source' => 'value',
          'default_value' => 0,
        ],
      ];

      $checked_value = explode("\n", $field_info['global_settings']['allowed_values'])[1];
      if (strpos($checked_value, '|') !== FALSE) {
        $checked_value = substr($checked_value, 0, strpos($checked_value, '|'));
      }
      $process['value']['map'][$checked_value] = 1;
    }
    else {
      // See \Drupal\migrate_drupal\Plugin\migrate\source\d6\User::baseFields(),
      // signature_format for an example of the YAML that represents this
      // process array.
      $process = [
        'value' => 'value',
        'format' => [
          [
            'plugin' => 'static_map',
            'bypass' => TRUE,
            'source' => 'format',
            'map' => [0 => NULL],
          ],
          [
            'plugin' => 'skip_on_empty',
            'method' => 'process',
          ],
          [
            'plugin' => 'migration',
            'migration' => [
              'd6_filter_format',
              'd7_filter_format',
            ],
            'source' => 'format',
          ],
        ],
      ];
    }

    $process = [
      'plugin' => 'sub_process',
      'source' => $field_name,
      'process' => $process,
    ];
    $migration->setProcessOfProperty($field_name, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldType(Row $row) {
    $widget_type = $row->getSourceProperty('widget_type');
    $settings = $row->getSourceProperty('global_settings');

    if ($widget_type == 'text_textfield') {
      $field_type = $settings['text_processing'] ? 'text' : 'string';
      if (empty($settings['max_length']) || $settings['max_length'] > 255) {
        $field_type .= '_long';
      }
      return $field_type;
    }

    if ($widget_type == 'text_textarea') {
      $field_type = $settings['text_processing'] ? 'text_long' : 'string_long';
      return $field_type;
    }

    switch ($widget_type) {
      case 'optionwidgets_buttons':
      case 'optionwidgets_select':
        return 'list_string';
      case 'optionwidgets_onoff':
        return 'boolean';
      default:
        return parent::getFieldType($row);
    }
  }

}
