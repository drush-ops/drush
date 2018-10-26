<?php

namespace Drupal\devel\Plugin\Devel\Dumper;

use Drupal\devel\DevelDumperBase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * Provides a Symfony VarDumper dumper plugin.
 *
 * @DevelDumper(
 *   id = "var_dumper",
 *   label = @Translation("Symfony var-dumper"),
 *   description = @Translation("Wrapper for <a href='https://github.com/symfony/var-dumper'>Symfony var-dumper</a> debugging tool."),
 * )
 *
 */
class VarDumper extends DevelDumperBase {

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL) {
    $cloner = new VarCloner();
    $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();

    $output = fopen('php://memory', 'r+b');
    $dumper->dump($cloner->cloneVar($input), $output);
    $output = stream_get_contents($output, -1, 0);

    if ($name) {
      $output = $name . ' => ' . $output;
    }

    return $this->setSafeMarkup($output);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkRequirements() {
    return class_exists('Symfony\Component\VarDumper\Cloner\VarCloner', TRUE);
  }

}
