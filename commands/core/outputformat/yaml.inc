<?php

use Drush\Internal\Symfony\Yaml\Dumper;

/**
 * Output formatter 'yaml'
 *
 * @param $data
 *   The $data parameter is rendered in yaml
 * @param $metadata
 *
 * Code:
 *
 */
class drush_outputformat_yaml extends drush_outputformat {
  function format($input, $metadata) {
    $dumper = new Dumper();
    // Set Yaml\Dumper's default indentation for nested nodes/collections to
    // 2 spaces for consistency with Drupal coding standards.
    $dumper->setIndentation(2);
    // The level where you switch to inline YAML is set to PHP_INT_MAX to
    // ensure this does not occur.
    $output = $dumper->dump($input, PHP_INT_MAX, NULL, NULL, TRUE);
    return $output;
  }
}
