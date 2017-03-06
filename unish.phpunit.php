#!/usr/bin/env php
<?php

/**
 * This script launches phpunit and points to the Site-Under-Test that's been built already.
 */

require __DIR__. '/tests/unish.inc';
unish_env();

// Get the arguments for the command.
$arguments = $GLOBALS['argv'];
// Shift off argv[0] which contains the name of this script.
array_shift($arguments);
// Add the directory containing the phpunit bootstrap file.
array_unshift($arguments, UNISH_DRUSH_DIR . '/tests');
$cmd = escapeshellarg(dirname(UNISH_SANDBOX) . '/drush-sut/vendor/bin/phpunit') . ' --configuration ' . implode(' ', $arguments);
fwrite(STDERR, 'Executing: ' . $cmd . "\n");
passthru($cmd, $return);
exit($return);