<?php

namespace Drupal\devel;

/**
 * Interface DevelDumperManagerInterface
 */
interface DevelDumperManagerInterface {

  /**
   * Dumps information about a variable.
   *
   * @param mixed $input
   *   The variable to dump.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $plugin_id
   *   (optional) The plugin ID, defaults to NULL.
   */
  public function dump($input, $name = NULL, $plugin_id = NULL);

  /**
   * Returns a string representation of a variable.
   *
   * @param mixed $input
   *   The variable to dump.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $plugin_id
   *   (optional) The plugin ID, defaults to NULL.
   *
   * @return string
   *   String representation of a variable.
   */
  public function export($input, $name = NULL, $plugin_id = NULL);

  /**
   * Sets a message with a string representation of a variable.
   *
   * @param mixed $input
   *   The variable to dump.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $type
   *   (optional) The message's type. Defaults to 'status'.
   * @param string $plugin_id
   *   (optional) The plugin ID, defaults to NULL.
   */
  public function message($input, $name = NULL, $type = 'status', $plugin_id = NULL);

  /**
   * Logs a variable to a drupal_debug.txt in the site's temp directory.
   *
   * @param mixed $input
   *   The variable to log to the drupal_debug.txt log file.
   * @param string $name
   *   (optional) If set, a label to output before $data in the log file.
   * @param string $plugin_id
   *   (optional) The plugin ID, defaults to NULL.
   *
   * @return void|false
   *   Empty if successful, FALSE if the log file could not be written.
   *
   * @see dd()
   * @see http://drupal.org/node/314112
   */
  public function debug($input, $name = NULL, $plugin_id = NULL);

  /**
   * Wrapper for ::dump() and ::export().
   *
   * @param mixed $input
   *   The variable to dump.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param bool $export
   *   (optional) Whether return string representation of a variable.
   * @param string $plugin_id
   *   (optional) The plugin ID, defaults to NULL.
   *
   * @return string|null
   *   String representation of a variable if $export is set to TRUE,
   *   NULL otherwise.
   */
  public function dumpOrExport($input, $name = NULL, $export = TRUE, $plugin_id = NULL);

  /**
   * Returns a render array representation of a variable.
   *
   * @param mixed $input
   *   The variable to export.
   * @param string $name
   *   (optional) The label to output before variable, defaults to NULL.
   * @param string $plugin_id
   *   (optional) The plugin ID, defaults to NULL.
   *
   * @return array
   *   String representation of a variable wrapped in a render array.
   */
  public function exportAsRenderable($input, $name = NULL, $plugin_id = NULL);

}
