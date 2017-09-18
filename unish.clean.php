#!/usr/bin/env php
<?php

/**
 * This script:
 *   - Builds the site-Under-Test
 *   - Runs phpunit.
 *
 * Supported arguments and options are the same as `phpunit`.
 */

$cmd = __DIR__ . '/unish.sut.php';
fwrite(STDERR, 'Executing: ' . $cmd . "\n");
passthru($cmd, $return);
if ($return) exit($return);

// Get the arguments for the command.
$arguments = $GLOBALS['argv'];
// Shift off argv[0] which contains the name of this script.
array_shift($arguments);
$cmd = __DIR__ . '/unish.phpunit.php ' . implode(' ', $arguments);
fwrite(STDERR, 'Executing: ' . $cmd . "\n");
passthru($cmd, $return);
if ($return) exit($return);
