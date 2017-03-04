<?php

/**
 * @file
 *   Initialize a sandboxed environment. Starts with call unish_init() at bottom.
 */


// Nothing to do here. PHPUnit already loaded autoloader. It is at PHPUNIT_COMPOSER_INSTALL.
if (0) {
  $msg = "Unable to load autoload.php.";
  fwrite(STDERR, $msg);
  return FALSE;
}