<?php

/**
 * @file
 * A multi-pass update followed by an update that installs a module, for
 * tests/kernel/UpdatedbTest.php.
 */

declare(strict_types=1);

/**
 * Batched update that finishes on its third pass.
 */
function drush_empty_module_update_8002(array &$sandbox): string
{
    $sandbox['pass'] = ($sandbox['pass'] ?? 0) + 1;
    $sandbox['#finished'] = $sandbox['pass'] / 3;
    return "Fixture pass {$sandbox['pass']} of 3";
}

/**
 * Installs a module, which rebuilds the container mid-run.
 */
function drush_empty_module_update_8003(): string
{
    \Drupal::service('module_installer')->install(['help']);
    return \Drupal::moduleHandler()->moduleExists('help') ? 'Fixture installed help' : 'Fixture failed to install help';
}
