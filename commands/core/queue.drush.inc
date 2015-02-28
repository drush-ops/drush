<?php

/**
 * Implements hook_drush_help().
 */
function queue_drush_help($section) {
  switch ($section) {
    case 'drush:queue-run':
      return dt('Run Drupal queue workers. As opposed to "drush cron" that can only be run one at a time on a single site, "drush queue-run" can be invoked as many times as the server load allows.');
  }
}

/**
 * Implements hook_drush_command().
 */
function queue_drush_command() {
  $items['queue-run'] = array(
    'description' => 'Run a specific queue by name',
    'arguments' => array(
      'queue_name' => 'The name of the queue to run, as defined in either hook_queue_info or hook_cron_queue_info.',
    ),
    'required-arguments' => TRUE,
  );
  $items['queue-list'] = array(
    'description' => 'Returns a list of all defined queues',
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'csv',
      'field-labels' => array(
        'queue' => 'Queue',
        'items' => 'Items',
        'class' => 'Class',
      ),
      'ini-item' => 'items',
      'table-metadata' => array(
        'key-value-item' => 'items',
      ),
      'output-data-type' => 'format-table',
    ),
  );

  return $items;
}

/**
 * Validation callback for drush queue-run.
 */
function drush_queue_run_validate($queue_name) {
  $queue = drush_queue_get_class();
  $queue->validate('queue-run');
}

/**
 * Validation callback for drush queue-list.
 */
function drush_queue_list_validate() {
  $queue = drush_queue_get_class();
  $queue->validate('queue-list');
}

/**
 * Return the appropriate queue class.
 */
function drush_queue_get_class() {
  return drush_get_class('Drush\Queue\Queue');
}

/**
 * Command callback for drush queue-run.
 *
 * Queue runner that is compatible with queues declared using both
 * hook_queue_info() and hook_cron_queue_info().
 *
 * @param $queue_name
 *   Arbitrary string. The name of the queue to work with.
 */
function drush_queue_run($queue_name) {
  $queue = drush_queue_get_class();
  $queue->run($queue_name);
}

/**
 * Command callback for drush queue-list.
 */
function drush_queue_list() {
  $queue = drush_queue_get_class();
  return $queue->listQueues();
}

