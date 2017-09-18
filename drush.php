<?php
/**
 * @file
 * Drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 5.6.0, or newer.
 */

require __DIR__ . '/includes/preflight.inc';
exit(drush_main());
