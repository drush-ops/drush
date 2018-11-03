<?php

namespace Drupal\migrate_cckfield_plugin_manager_test\Plugin\migrate\cckfield;

use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * @MigrateCckField(
 *   id = "d6_file",
 *   core = {6},
 *   type_map = {
 *     "file" = "file"
 *   },
 *   source_module = "foo",
 *   destination_module = "bar"
 * )
 */
class D6FileField extends CckFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $migration->setProcessOfProperty($field_name, [
      'class' => static::class,
    ]);
  }

}
