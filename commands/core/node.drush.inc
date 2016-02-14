<?php

/**
 * @file
 * Node commands.
 */

use Drush\Log\LogLevel;

/**
 * Implements hook_drush_command().
 */
function node_drush_command() {
  $items = [];

  $items['node-access-rebuild'] = [
    'description' => 'Rebuild node access permissions.',
    'aliases' => ['nar'],
  ];

  return $items;
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_node_access_rebuild() {
  node_access_rebuild();
  drush_log(dt('Node access permissions rebuild complete.'), LogLevel::OK);
}
