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

  return $items;
}

/**
 * Call the correct queue factory method
 *
 * @deprecated
 */
function drush_queue_get($name) {
  return DrupalQueue::get($name);
}

/**
 * Validation for Drupal 6 to ensure the drupal_queue module is enabled.
 *
 * @param $command
 *   The command being validated.
 */
function drush_queue_validate($command) {
  if (drush_drupal_major_version() == 6) {
    if (!module_exists('drupal_queue')) {
      $args = array('!command' => $command, '!dependencies' => 'drupal_queue');
      return drush_set_error('DRUSH_COMMAND_DEPENDENCY_ERROR', dt('Command !command needs the following modules installed/enabled to run: !dependencies.', $args));
    }
    else {
      drupal_queue_include();
    }
  }
}

/**
 * Validation callback for drush queue-run.
 */
function drush_queue_run_validate($queue_name) {
  drush_queue_validate('queue-run');

  // Get all queues.
  $queues = drush_queue_get_queues();
  if (!isset($queues[$queue_name])) {
    return drush_set_error('DRUSH_QUEUE_ERROR', dt('Could not find the !name queue.', array('!name' => $queue_name)));
  }
}

/**
 * Validation callback for drush queue-list.
 */
function drush_queue_list_validate() {
  drush_queue_validate('queue-list');
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
  // Get all queues.
  $queues = drush_queue_get_queues();

  $info = $queues[$queue_name];
  $function = $info['worker callback'];
  $time_limit = (int) drush_get_option('time-limit');
  $end = time() + $time_limit;
  /** @var DrupalQueueInterface $queue */
  $queue = DrupalQueue::get($queue_name);
  $count = 0;

  $start = microtime(TRUE);
  while ((!$time_limit || time() < $end) && ($item = $queue->claimItem())) {
    try {
      drush_log(dt('Processing item @id from @name queue.', array('@name' => $queue_name, 'id' => $item->item_id)), 'info');
      $function($item->data);
      $queue->deleteItem($item);
      $count++;
    }
    catch (Exception $e) {
      // In case of exception log it and leave the item in the queue
      // to be processed again later.
      drush_set_error('DRUSH_QUEUE_EXCEPTION', $e->getMessage());
    }
  }
  $elapsed = microtime(TRUE) - $start;

  drush_log(dt('Processed @count items from the @name queue in @elapsed sec.', array('@count' => $count, '@name' => $queue_name, '@elapsed' => round($elapsed, 2))), drush_get_error() ? 'warning' : 'ok');
}

/**
 * Command callback for drush queue-list.
 */
function drush_queue_list() {
  $result = array();
  $queues = drush_queue_get_queues();
  foreach (array_keys($queues) as $name) {
    /** @var DrupalQueueInterface $q */
    $q = DrupalQueue::get($name);
    $result[$name] = array(
      'queue' => $name,
      'items' => $q->numberOfItems(),
      'class' => get_class($q),
    );
  }
  return $result;
}

/**
 * Get queues defined with hook_cron_queue_info().
 *
 * @return array
 *   Array of queues indexed by name and containing queue object and number
 *   of items.
 */
function drush_queue_get_queues() {
  static $queues;

  if (!isset($queues)) {
    $queues = module_invoke_all('cron_queue_info');
    drupal_alter('cron_queue_info', $queues);
    // Merge in queues from modules that implement hook_queue_info.
    // Currently only defined by the queue_ui module.
    $info_queues = module_invoke_all('queue_info');
    foreach ($info_queues as $name => $queue) {
      $queues[$name]['worker callback'] = $queue['cron']['callback'];
      if (isset($queue['cron']['time'])) {
        $queues[$name]['time'] = $queue['cron']['time'];
      }
    }
  }

  return $queues;
}
