<?php

define('DRUSH_YAML_VERSION', '2.2.1');

if (!class_exists('Symfony\Component\Yaml\Dumper')) {
  // For Drush PSR-0 and Composer information, see http://drupal.org/node/1316322.
  $path = DRUSH_BASE_PATH . '/lib/Yaml-' . DRUSH_YAML_VERSION;
  require_once "$path/Dumper.php";
  require_once "$path/Inline.php";
  require_once "$path/Escaper.php";
}

use Symfony\Component\Yaml\Dumper;

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
    $output = $dumper->dump($input, PHP_INT_MAX);
    return $output;
  }
}
