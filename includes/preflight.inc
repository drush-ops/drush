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
