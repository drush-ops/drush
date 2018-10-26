<?php

namespace Drupal\devel_dumper_test\Plugin\Devel\Dumper;

use Drupal\devel\DevelDumperBase;

/**
 * Provides a AvailableTestDumper plugin.
 *
 * @DevelDumper(
 *   id = "available_test_dumper",
 *   label = @Translation("Available test dumper."),
 *   description = @Translation("Drupal dumper for testing purposes (available).")
 * )
 */
class AvailableTestDumper extends DevelDumperBase {

  /**
   * {@inheritdoc}
   */
  public function dump($input, $name = NULL) {
    // Add a predetermined string to $input to check if this dumper has been
    // selected successfully.
    $input = '<pre>' . 'AvailableTestDumper::dump() ' . $input . '</pre>';
    echo $input;
  }

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL) {
    // Add a predetermined string to $input to check if this dumper has been
    // selected successfully.
    $input = '<pre>' . 'AvailableTestDumper::export() ' . $input . '</pre>';
    return $this->setSafeMarkup($input);
  }

  /**
   * {@inheritdoc}
   */
  public function exportAsRenderable($input, $name = NULL) {
    // Add a predetermined string to $input to check if this dumper has been
    // selected successfully.
    $input = '<pre>' . 'AvailableTestDumper::exportAsRenderable() ' . $input . '</pre>';

    return [
      '#attached' => [
        'library' => ['devel_dumper_test/devel_dumper_test']
      ],
      '#markup' => $this->setSafeMarkup($input),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function checkRequirements() {
    return TRUE;
  }
}
