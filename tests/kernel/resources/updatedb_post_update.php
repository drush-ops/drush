<?php

/**
 * @file
 * A pending post update for tests/kernel/UpdatedbTest.php.
 *
 * The post update registry finds hooks by scanning defined functions, so
 * including this file registers a pending post update for drush_empty_module.
 */

declare(strict_types=1);

/**
 * Fixture post update.
 */
function drush_empty_module_post_update_fixture(): string
{
    return 'The post update ran';
}
