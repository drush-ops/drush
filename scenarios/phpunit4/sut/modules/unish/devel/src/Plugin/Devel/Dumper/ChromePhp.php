<?php

namespace Drupal\devel\Plugin\Devel\Dumper;

use Drupal\devel\DevelDumperBase;

/**
 * Provides a ChromePhp dumper plugin.
 *
 * @DevelDumper(
 *   id = "chromephp",
 *   label = @Translation("ChromePhp"),
 *   description = @Translation("Wrapper for <a href='https://craig.is/writing/chrome-logger'>ChromePhp</a> debugging tool.")
 * )
 */
class ChromePhp extends DevelDumperBase {

  /**
   * {@inheritdoc}
   */
  public function dump($input, $name = NULL) {
    \ChromePhp::log($input);
  }

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL) {
    $this->dump($input);
    return $this->t('Dump was redirected to the console.');
  }

  /**
   * {@inheritdoc}
   */
  public static function checkRequirements() {
    return class_exists('ChromePhp', TRUE);
  }

}
