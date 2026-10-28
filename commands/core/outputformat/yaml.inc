<?php

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

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
    // Indentation of 2 spaces for consistency with Drupal coding standards.
    // PHP_INT_MAX inline level ensures output is never collapsed to inline YAML.
    $dumper = new Dumper(2);
    $output = $dumper->dump($input, PHP_INT_MAX, 0, Yaml::DUMP_OBJECT_AS_MAP);
    return $output;
  }
}
