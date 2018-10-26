<?php

namespace Drupal\devel_dumper_test\Plugin\Devel\Dumper;

use Drupal\devel\DevelDumperBase;

/**
 * Provides a NotAvailableTestDumper plugin.
 *
 * @DevelDumper(
 *   id = "not_available_test_dumper",
 *   label = @Translation("Not available test dumper."),
 *   description = @Translation("Drupal dumper for testing purposes (not available).")
 * )
 */
class NotAvailableTestDumper extends DevelDumperBase {

  /**
   * {@inheritdoc}
   */
  public function dump($input, $name = NULL) {
    $input = '<pre>' . $input . '</pre>';
    echo $input;
  }

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL) {
    $input = '<pre>' . $input . '</pre>';
    return $this->setSafeMarkup($input);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkRequirements() {
    return FALSE;
  }

}
