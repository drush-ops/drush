<?php

/**
 * @file
 * A script for tests/kernel/PhpTest.php. $extra holds the script's arguments.
 */

declare(strict_types=1);

return ['system' => \Drupal::service('module_handler')->moduleExists('system'), 'extra' => $extra];
