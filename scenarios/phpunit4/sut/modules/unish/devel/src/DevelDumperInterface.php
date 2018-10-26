<?php

namespace Drupal\devel;

/**
 * Base interface definition for DevelDumper plugins.
 *
 * @see \Drupal\devel\Annotation\DevelDumper
 * @see \Drupal\devel\DevelDumperPluginManager
 * @see \Drupal\devel\DevelDumperBase
 * @see plugin_api
 */
interface DevelDumperInterface {

  /**
   * Dumps information about a variable.
   *
   * @param mixed $input
   *   The variable to dump.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   */
  public function dump($input, $name = NULL);

  /**
   * Returns a string representation of a variable.
   *
   * @param mixed $input
   *   The variable to export.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   *
   * @return string
   *   String representation of a variable.
   */
  public function export($input, $name = NULL);

  /**
   * Returns a string representation of a variable wrapped in a render array.
   *
   * @param mixed $input
   *   The variable to export.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   *
   * @return array
   *   String representation of a variable wrapped in a render array.
   */
  public function exportAsRenderable($input, $name = NULL);

  /**
   * Checks if requirements for this plugin are satisfied.
   *
   * @return bool
   *   TRUE is requirements are satisfied, FALSE otherwise.
   */
  public static function checkRequirements();

}
