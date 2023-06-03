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
  // See https://getcomposer.org/doc/articles/vendor-binaries.md#finding-the-composer-autoloader-from-a-binary.
  if (isset($_composer_bin_dir) && isset($_composer_autoload_path)) {
      require dirname(__DIR__) . '/drush.php';
  }
  else {
      throw new RuntimeException('Drush Launcher is not compatible with Drush 12+. See https://github.com/drush-ops/drush-launcher/issues/105.');
  }
}
