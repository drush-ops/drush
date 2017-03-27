<?php

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;

class StateCommands {

  /**
   * Display a state value.
   *
   * @command state-get
   *
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @param string $key The key name.
   * @usage drush state-get system.cron_last
   *   Displays last cron run timestamp
   * @aliases sget
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   */
  public function get($key, $options = ['format' => 'string', 'fields' => '']) {
    $value = \Drupal::state()->get($key);
    return new PropertyList([$key => $value]);
  }

  /**
   * Set a state value.
   *
   * @command state-set
   *
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @param string $key The state key, for example: system.cron_last.
   * @param mixed $value The value to assign to the state key. Use '-' to read from STDIN.
   * @option input-format Type for the value. Defaults to 'auto'. Other recognized values: string, integer float, boolean, json, yaml.
   * @option value For internal use only.
   * @hidden-options value
   * @usage drush sset system.maintenance_mode 1 --input-format=integer
   *  Put site into Maintenance mode.
   * @usage drush state-set system.cron_last 1406682882 --input-format=integer
   *  Sets a timestamp for last cron run.
   * @usage php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush state-set --input-format=json foo.name -
   *   Set a key to a complex value (e.g. array)
   * @aliases sset
   *
   * @return void
   */
  public function set($key, $value, $options = ['input-format' => 'auto', 'value' => NULL]) {
    // A convenient way to pass a multiline value within a backend request.
    $value = $options['value'] ?: $value;

    if (!isset($value)) {
      throw new \Exception(dt('No state value specified.'));
      return NULL;
    }

    // Special flag indicating that the value has been passed via STDIN.
    if ($value === '-') {
      $value = stream_get_contents(STDIN);
    }

    // If the value is a string (usual case, unless we are called from code),
    // then format the input.
    if (is_string($value)) {
      $value = drush_value_format($value, $options['input-format']);
    }

    \Drupal::state()->set($key, $value);
  }

  /**
   * Delete a state entry.
   *
   * @command state-delete
   *
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @param string $key The state key, for example "system.cron_last".
   * @usage drush state-del system.cron_last
   *   Delete state entry for system.cron_last.
   * @aliases sdel
   *
   * @return void
   */
  public function delete($key) {
    \Drupal::state()->delete($key);
  }
}
