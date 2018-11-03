<?php

namespace Drupal\field\Plugin\migrate\source\d6;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * The field instance per form display source class.
 *
 * @MigrateSource(
 *   id = "d6_field_instance_per_form_display",
 *   source_module = "content"
 * )
 */
class FieldInstancePerFormDisplay extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $rows = [];
    $result = $this->prepareQuery()->execute();
    while ($field_row = $result->fetchAssoc()) {
      $bundle = $field_row['type_name'];
      $field_name = $field_row['field_name'];

      $index = "$bundle.$field_name";
      $rows[$index]['type_name'] = $bundle;
      $rows[$index]['widget_active'] = (bool) $field_row['widget_active'];
      $rows[$index]['field_name'] = $field_name;
      $rows[$index]['type'] = $field_row['type'];
      $rows[$index]['module'] = $field_row['module'];
      $rows[$index]['weight'] = $field_row['weight'];
      $rows[$index]['widget_type'] = $field_row['widget_type'];
      $rows[$index]['widget_settings'] = unserialize($field_row['widget_settings']);
      $rows[$index]['display_settings'] = unserialize($field_row['display_settings']);
    }

    return new \ArrayIterator($rows);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('content_node_field_instance', 'cnfi')
      ->fields('cnfi', [
        'field_name',
        'type_name',
        'weight',
        'label',
        'widget_type',
        'widget_settings',
        'display_settings',
        'description',
        'widget_module',
        'widget_active',
      ])
      ->fields('cnf', [
        'type',
        'module',
      ]);
    $query->join('content_node_field', 'cnf', 'cnfi.field_name = cnf.field_name');
    $query->orderBy('cnfi.weight');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'field_name' => $this->t('The machine name of field.'),
      'type_name' => $this->t('Content type where this field is used.'),
      'weight' => $this->t('Weight.'),
      'label' => $this->t('A name to show.'),
      'widget_type' => $this->t('Widget type.'),
      'widget_settings' => $this->t('Serialize data with widget settings.'),
      'display_settings' => $this->t('Serialize data with display settings.'),
      'description' => $this->t('A description of field.'),
      'widget_module' => $this->t('Module that implements widget.'),
      'widget_active' => $this->t('Status of widget'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['type_name']['type'] = 'string';
    $ids['field_name']['type'] = 'string';
    return $ids;
  }

}
