<?php

namespace Drupal\link\Plugin\migrate\cckfield;

@trigger_error('LinkField is deprecated in Drupal 8.3.x and will be be removed before Drupal 9.0.x. Use \Drupal\link\Plugin\migrate\field\d6\LinkField instead.', E_USER_DEPRECATED);

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;

/**
 * @MigrateCckField(
 *   id = "link",
 *   core = {6},
 *   type_map = {
 *     "link_field" = "link"
 *   },
 *   source_module = "link",
 *   destination_module = "link"
 * )
 *
 * @deprecated in Drupal 8.3.x and will be removed in Drupal 9.0.x. Use
 * \Drupal\link\Plugin\migrate\field\d6\LinkField instead.
 *
 * @see https://www.drupal.org/node/2751897
 */
class LinkField extends CckFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    // See d6_field_formatter_settings.yml and FieldPluginBase
    // alterFieldFormatterMigration().
    return [
      'default' => 'link',
      'plain' => 'link',
      'absolute' => 'link',
      'title_plain' => 'link',
      'url' => 'link',
      'short' => 'link',
      'label' => 'link',
      'separate' => 'link_separate',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'd6_cck_link',
      'source' => $field_name,
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

}
