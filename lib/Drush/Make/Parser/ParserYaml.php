<?php

/**
 * @file
 * Parser for YAML format.
 */

namespace Drush\Make\Parser;

use Drush\Internal\Symfony\Yaml\Yaml;

class ParserYaml implements ParserInterface {

  /**
   * {@inheritdoc}
   */
  public static function supportedFile($filename) {
    $info = pathinfo($filename);
    return isset($info['extension']) && $info['extension'] === 'yml';
  }

  /**
   * {@inheritdoc}
   */
  public static function parse($data) {
    return Yaml::parse($data);
  }

}
