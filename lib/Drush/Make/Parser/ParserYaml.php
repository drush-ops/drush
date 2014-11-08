<?php

/**
 * @file
 * Parser for YAML format.
 */

namespace Drush\Make\Parser;

use Symfony\Component\Yaml\Yaml;

class ParserYaml implements ParserInterface {

  /**
   * {@inheritdoc}
   */
  public static function supportedFile($filename) {
    // @todo remove this and allow support for stdin in YAML files too.
    if ($filename === '-') {
      return TRUE;
    }
    $info = pathinfo($filename);
    return $info['extension'] === 'make';
  }

  /**
   * {@inheritdoc}
   */
  public function parse($data) {
    return Yaml::parse($data);
  }

}
