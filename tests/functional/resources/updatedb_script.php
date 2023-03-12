<?php

declare(strict_types=1);

// Set the schema version of drush_empty_module to a lower version, so we have a
// pending update.
$current = \Drupal::service('update.update_hook_registry')->getInstalledVersion('drush_empty_module');
\Drupal::service('update.update_hook_registry')->setInstalledVersion('drush_empty_module', $current - 1);
