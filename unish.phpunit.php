#!/usr/bin/env php
<?php

/**
 * This script launches phpunit and points to the Site-Under-Test that's been built already.
 */

require __DIR__. '/tests/unish.inc';
list($unish_tmp, $unish_sandbox, $unish_drush_dir) = unishGetPaths();

// Get the arguments for the command.
$arguments = $GLOBALS['argv'];
// Shift off argv[0] which contains the name of this script.
array_shift($arguments);
// Add the directory containing the phpunit bootstrap file.
array_unshift($arguments, $unish_drush_dir . '/tests');
$cmd = escapeshellarg(dirname($unish_sandbox) . '/drush-sut/vendor/bin/phpunit') . ' --configuration ' . implode(' ', $arguments);
fwrite(STDERR, 'Executing: ' . $cmd . "\n");
passthru($cmd, $return);
exit($return);
