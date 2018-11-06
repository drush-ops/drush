<?php

// Set the schema version of devel to a lower version, so we have a pending update.
$current = drupal_get_installed_schema_version('devel');
drupal_set_installed_schema_version('devel', $current - 1);
