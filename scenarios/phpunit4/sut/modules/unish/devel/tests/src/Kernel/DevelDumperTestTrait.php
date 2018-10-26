<?php

namespace Drupal\Tests\devel\Kernel;

/**
 * Provides a class for checking dumper output.
 */
trait DevelDumperTestTrait {

  /**
   * Asserts that the string passed in input is equals to the string
   * representation of a variable obtained exporting the data.
   *
   * Use \Drupal\devel\DevelDumperManager::export().
   *
   * @param $dump
   *   The string that contains the dump output to test.
   * @param $data
   *   The variable to dump.
   * @param null $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $message
   *   (optional) A message to display with the assertion.
   */
  public function assertDumpExportEquals($dump, $data, $name = NULL, $message = '') {
    $output = $this->getDumperExportDump($data, $name);
    $this->assertEqual(rtrim($dump), $output, $message);
  }

  /**
   * Asserts that a haystack contains the dump export output.
   *
   * Use \Drupal\devel\DevelDumperManager::export().
   *
   * @param $haystack
   *   The string that contains the dump output to test.
   * @param $data
   *   The variable to dump.
   * @param null $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $message
   *   (optional) A message to display with the assertion.
   */
  public function assertContainsDumpExport($haystack, $data, $name = NULL, $message = '') {
    $output = $this->getDumperExportDump($data, $name);
    $this->assertContains($output, (string) $haystack, $message);
  }

  /**
   * Asserts that the string passed in input is equals to the string
   * representation of a variable obtained dumping the data.
   *
   * Use \Drupal\devel\DevelDumperManager::dump().
   *
   * @param $dump
   *   The string that contains the dump output to test.
   * @param $data
   *   The variable to dump.
   * @param null $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $message
   *   (optional) A message to display with the assertion.
   */
  public function assertDumpEquals($dump, $data, $name = NULL, $message = '') {
    $output = $this->getDumperDump($data, $name);
    $this->assertEqual(rtrim($dump), $output, $message);
  }

  /**
   * Asserts that a haystack contains the dump output.
   *
   * Use \Drupal\devel\DevelDumperManager::dump().
   *
   * @param $haystack
   *   The string that contains the dump output to test.
   * @param $data
   *   The variable to dump.
   * @param null $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $message
   *   (optional) A message to display with the assertion.
   */
  public function assertContainsDump($haystack, $data, $name = NULL, $message = '') {
    $output = $this->getDumperDump($data, $name);
    $this->assertContains($output, (string) $haystack, $message);
  }

  /**
   * Returns a string representation of a variable.
   *
   * @param mixed $input
   *   The variable to dump.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   *
   * @return string
   *   String representation of a variable.
   *
   * @see \Drupal\devel\DevelDumperManager::export()
   */
  private function getDumperExportDump($input, $name = NULL) {
    $output = \Drupal::service('devel.dumper')->export($input, $name);
    return rtrim($output);
  }

  /**
   * Returns a string representation of a variable.
   *
   * @param mixed $input
   *   The variable to dump.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   *
   * @return string
   *   String representation of a variable.
   *
   * @see \Drupal\devel\DevelDumperManager::dump()
   */
  private function getDumperDump($input, $name = NULL) {
    ob_start();
    \Drupal::service('devel.dumper')->dump($input, $name);
    $output = ob_get_contents();
    ob_end_clean();
    return rtrim($output);
  }

}
