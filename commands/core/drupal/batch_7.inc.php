<?php
/**
 * @file
 *   Drupal 7 engine for the Batch API
 */

include __DIR__ . '/batch_common.php';

// TODO: Make an engine just for db operations

function drush_db_next_id() {
  return \db_next_id();
}
