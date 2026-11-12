<?php

/**
 * @file
 * An update throwing a PHP Error, for tests/kernel/UpdatedbTest.php.
 */

declare(strict_types=1);

/**
 * Throws an Error, which core's update runner does not catch.
 */
function drush_empty_module_update_8002(): never
{
    throw new \TypeError('Fixture error in drush_empty_module_update_8002');
}
