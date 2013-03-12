<?php

// TODO: This Yaml formatter only works when Drupal 8 is bootstrapped.  :(
// It was ported mechanically from config.drush.inc, when it was
// adjusted to use Drush's output format engines.  It would be preferable
// to do this in a way that worked independently of Drupal 8.
// See: http://drupal.org/node/1366098
use Symfony\Component\Yaml\Yaml;
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
