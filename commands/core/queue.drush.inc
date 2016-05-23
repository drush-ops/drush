<?php

use Drush\Log\LogLevel;

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
    'options' => array(
      'time-limit' => 'The maximum number of seconds allowed to run the queue',
    ),
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
  $items['queue-delete'] = array(
    'description' => 'Delete all items in a specific queue',
    'arguments' => array(
      'queue_name' => 'The name of the queue to delete all items for, as defined in either hook_queue_info or hook_cron_queue_info.',
    ),
    'required-arguments' => TRUE,
  );

  return $items;
}

/**
 * Validation callback for drush queue-run.
 */
function drush_queue_run_validate($queue_name) {
  try {
    $queue = drush_queue_get_class();
    $queue->getInfo($queue_name);
  }
  catch (\Drush\Queue\QueueException $exception) {
    return drush_set_error('DRUSH_QUEUE_RUN_VALIDATION_ERROR', $exception->getMessage());
  }
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
  $time_limit = (int) drush_get_option('time-limit');
  $start = microtime(TRUE);
  $count = $queue->run($queue_name, $time_limit);
  $elapsed = microtime(TRUE) - $start;
  drush_log(dt('Processed @count items from the @name queue in @elapsed sec.', array('@count' => $count, '@name' => $queue_name, '@elapsed' => round($elapsed, 2))), drush_get_error() ? LogLevel::WARNING : LogLevel::OK);
}

/**
 * Command callback for drush queue-list.
 */
function drush_queue_list() {
  $queue = drush_queue_get_class();
  return $queue->listQueues();
}

/**
 * Validation callback for drush queue-delete.
 */
function drush_queue_delete_validate($queue_name) {
  try {
    $queue = drush_queue_get_class();
    $queue->getInfo($queue_name);
  }
  catch (\Drush\Queue\QueueException $exception) {
    return drush_set_error('DRUSH_QUEUE_DELETE_VALIDATION_ERROR', $exception->getMessage());
  }
}

/**
 * Command callback for drush queue-delete.
 *
 * @param $queue_name
 *   Arbitrary string. The name of the queue to work with.
 *
 * @return int
 */
function drush_queue_delete($queue_name) {
  $drush_queue = drush_queue_get_class();
  $queue = $drush_queue->getQueue($queue_name);
  $queue->deleteQueue();

  drush_log(dt('All items in @name queue deleted.', array('@name' => $queue_name)), LogLevel::OK);
}
