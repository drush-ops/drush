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
    'options' => array(
      'pipe' => 'Return a comma delimited list of queues.'
    )
  );

  return $items;
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

  // Try to increase the maximum execution time if it is too low.
  $max_execution_time = ini_get('max_execution_time');
  if ($max_execution_time > 0 && $max_execution_time < 240 && !ini_get('safe_mode')) {
    set_time_limit(240);
  }

  $info = $queues[$queue_name];
  $function = $info['cron']['callback'];
  $end = time() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
  $queue = DrupalQueue::get($queue_name);
  while (time() < $end && ($item = $queue->claimItem())) {
    $function($item->data);
    $queue->deleteItem($item);
  }
}

/**
 * Command callback for drush queue-list.
 */
function drush_queue_list() {
  $queues = drush_queue_get_queues();
  $default_class = variable_get('queue_default_class', 'SystemQueue');
  $rows = array(array('Queue', 'Items', 'Class'));
  foreach (array_keys($queues) as $name) {
    $q = DrupalQueue::get($name);
    $class = variable_get('queue_class_' . $name, $default_class);
    $rows[] = array($name, $q->numberOfItems(), $class);
  }

  if (drush_get_option('pipe')) {
    $pipe = array();
    array_shift($rows);
    foreach ($rows as $r) {
      $pipe[] = implode(",", $r);
    }
    drush_print_pipe($pipe);
  }
  else {
    drush_print_table($rows, TRUE);
  }

  // Return the result for backend invoke
  return $rows;
}

/**
 * Get queues defined with hook_cron_queue_info().
 *
 * @return
 *   Array of queues indexed by name and containing queue object and number
 *   of items.
 */
function drush_queue_get_queues() {
  static $queues;
  if (!isset($queues)) {
    // Invoke hook_queue_info(), currently only defined by the queue_ui module.
    $queues = module_invoke_all('queue_info');

    // Merge in queues from modules that implement hook_cron_queue_info().
    $cron_queues = module_invoke_all('cron_queue_info');
    drupal_alter('cron_queue_info', $cron_queues);
    foreach($cron_queues as $name => $queue) {
      $queues[$name] = array(
        'cron' => array(
          'callback' => $queue['worker callback'],
          'time' => $queue['time'],
        )
      );
    }
  }
  return $queues;
}
