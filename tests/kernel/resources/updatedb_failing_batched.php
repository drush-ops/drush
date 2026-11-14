<?php

/**
 * @file
 * A batched update failing on its second pass, for tests/kernel/UpdatedbTest.php.
 */

declare(strict_types=1);

use Drupal\Core\Utility\UpdateException;

/**
 * Batched update that returns a message, then fails.
 */
function drush_empty_module_update_8002(array &$sandbox): string
{
    $sandbox['pass'] = ($sandbox['pass'] ?? 0) + 1;
    if ($sandbox['pass'] === 2) {
        throw new UpdateException('Fixture failure on pass 2');
    }
    $sandbox['#finished'] = 0.5;
    return 'Fixture pass 1 message';
}

/**
 * Never runs: an earlier update of the module aborts.
 */
function drush_empty_module_update_8003(): void
{
}
