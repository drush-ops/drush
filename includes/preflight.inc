<?php

/**
 * @file
 * Postflight and shutdown code.
 */

use Drush\Drush;
use Drush\Runtime\Runtime;

/**
 * The main Drush function.
 *
 * This function is still called by drush-launcher. It is no longer used by
 * Drush itself.
 */
function drush_main() {
  require dirname(__DIR__) . '/drush.php';
}

/**
 * Shutdown function to save code coverage data.
 */
function drush_coverage_shutdown() {
  if ($file_name = drush_get_context('DRUSH_CODE_COVERAGE', FALSE)) {
    $data = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();

    // If coverage dump file contains anything, merge in the old data before
    // saving. This happens if the current drush command invoked another drush
    // command.
    if (file_exists($file_name) && $content = file_get_contents($file_name)) {
      $merge_data = unserialize($content);
      if (is_array($merge_data)) {
        foreach ($merge_data as $file => $lines) {
          if (!isset($data[$file])) {
            $data[$file] = $lines;
          }
          else {
            foreach ($lines as $num => $executed) {
              if (!isset($data[$file][$num])) {
                $data[$file][$num] = $executed;
              }
              else {
                $data[$file][$num] = ($executed == 1 ? $executed : $data[$file][$num]);
              }
            }
          }
        }
      }
    }

    file_put_contents($file_name, serialize($data));
  }
}
