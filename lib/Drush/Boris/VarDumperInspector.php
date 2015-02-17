<?php

/**
 * @file
 * Contains \Drush\Boris\VarDumperInspector.
 */

namespace Drush\Boris;

use Symfony\Component\VarDumper\VarDumper;

/**
 * Boris inspector class for the Symfony var dumper.
 */
class VarDumperInspector {

  public function inspect($variable) {
    return VarDumper::dump($variable);
  }
}
