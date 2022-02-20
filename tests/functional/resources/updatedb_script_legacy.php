<?php

// Set the schema version of drush_empty_module to a lower version, so we have a
// pending update.
$current = drupal_get_installed_schema_version('drush_empty_module');
drupal_set_installed_schema_version('drush_empty_module', $current - 1);
