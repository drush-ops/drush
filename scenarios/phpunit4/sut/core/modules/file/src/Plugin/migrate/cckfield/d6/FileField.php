<?php

namespace Drupal\file\Plugin\migrate\cckfield\d6;

@trigger_error('FileField is deprecated in Drupal 8.3.x and will be removed before Drupal 9.0.x. Use \Drupal\file\Plugin\migrate\field\d6\FileField instead.', E_USER_DEPRECATED);

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;

/**
 * @MigrateCckField(
 *   id = "filefield",
 *   core = {6},
 *   source_module = "filefield",
 *   destination_module = "file"
 * )
 *
 *  @deprecated in Drupal 8.3.x, to be removed before Drupal 9.0.x. Use
 * \Drupal\file\Plugin\migrate\field\d6\FileField instead.
 *
 * @see https://www.drupal.org/node/2751897
 */
class FileField extends CckFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'filefield_widget' => 'file_generic',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'default' => 'file_default',
      'url_plain' => 'file_url_plain',
      'path_plain' => 'file_url_plain',
      'image_plain' => 'image',
      'image_nodelink' => 'image',
      'image_imagelink' => 'image',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'd6_cck_file',
      'source' => $field_name,
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldType(Row $row) {
    return $row->getSourceProperty('widget_type') == 'imagefield_widget' ? 'image' : 'file';
  }

}
