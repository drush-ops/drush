<?php

/**
 * @file
 * A failing update for tests/kernel/UpdatedbTest.php.
 */

declare(strict_types=1);

use Drupal\Core\Utility\UpdateException;

/**
 * Failing update.
 */
function drush_empty_module_update_8002(): never
{
    throw new UpdateException('Fixture failure in drush_empty_module_update_8002');
}
