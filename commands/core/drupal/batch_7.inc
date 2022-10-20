<?php
/**
 * @file
 *   Drupal 7 engine for the Batch API
 */

use Drush\Log\LogLevel;

/**
 * Main loop for the Drush batch API.
 *
 * Saves a record of the batch into the database, and progressively call $command to
 * process the operations.
 *
 * @param string $command
 *    The command to call to process the batch.
 * @param array $args
 * @param array $options
 */
function _drush_backend_batch_process($command, $args, $options) {
  $batch =& batch_get();

  if (isset($batch)) {
    $process_info = array(
      'current_set' => 0,
    );
    $batch += $process_info;

    // The batch is now completely built. Allow other modules to make changes
    // to the batch so that it is easier to reuse batch processes in other
    // enviroments.
    drupal_alter('batch', $batch);

    // Assign an arbitrary id: don't rely on a serial column in the 'batch'
    // table, since non-progressive batches skip database storage completely.
    $batch['id'] = db_next_id();
    $args[] = $batch['id'];

    $batch['progressive'] = TRUE;

    // Move operations to a job queue. Non-progressive batches will use a
    // memory-based queue.
    foreach ($batch['sets'] as $key => $batch_set) {
      _batch_populate_queue($batch, $key);
    }

    drush_include_engine('drupal', 'environment');
    // Store the batch.
    db_insert('batch')
      ->fields(array(
        'bid' => $batch['id'],
        'timestamp' => REQUEST_TIME,
        'token' => drush_get_token($batch['id']),
        'batch' => serialize($batch),
      ))
      ->execute();
    $finished = FALSE;

    // Not used in D8+ and possibly earlier.
    global $user;

    while (!$finished) {
      $data = drush_invoke_process('@self', $command, $args, array('user' => drush_user_get_class()->getCurrentUserAsSingle()->id()));

      $finished = drush_get_error() || !$data || (isset($data['context']['drush_batch_process_finished']) && $data['context']['drush_batch_process_finished'] == TRUE);
    }
  }
}


/**
 * Initialize the batch command and call the worker function.
 *
 * Loads the batch record from the database and sets up the requirements
 * for the worker, such as registering the shutdown function.
 *
 * @param id
 *   The batch id of the batch being processed.
 */
function _drush_batch_command($id) {
  $batch =& batch_get();

  $data = db_query("SELECT batch FROM {batch} WHERE bid = :bid", array(
    ':bid' => $id))->fetchField();

  if ($data) {
    $batch = unserialize($data);
  }
  else {
    return FALSE;
  }

  if (!isset($batch['running'])) {
    $batch['running'] = TRUE;
  }

  // Register database update for end of processing.
  register_shutdown_function('_drush_batch_shutdown');

  if (_drush_batch_worker()) {
    _drush_batch_finished();
  }
}


/**
 * Process batch operations
 *
 * Using the current $batch process each of the operations until the batch
 * has been completed or half of the available memory for the process has been
 * reached.
 */
function _drush_batch_worker() {
  $batch =& batch_get();
  $current_set =& _batch_current_set();
  $set_changed = TRUE;

  if (empty($current_set['start'])) {
    $current_set['start'] = microtime(TRUE);
  }
  $queue = _batch_queue($current_set);
  while (!$current_set['success']) {
    // If this is the first time we iterate this batch set in the current
    // request, we check if it requires an additional file for functions
    // definitions.
    if ($set_changed && isset($current_set['file']) && is_file($current_set['file'])) {
      include_once DRUPAL_ROOT . '/' . $current_set['file'];
    }

    $task_message = '';
    // Assume a single pass operation and set the completion level to 1 by
    // default.
    $finished = 1;

    if ($item = $queue->claimItem()) {
      list($function, $args) = $item->data;

      // Build the 'context' array and execute the function call.
      $batch_context = array(
        'sandbox'  => &$current_set['sandbox'],
        'results'  => &$current_set['results'],
        'finished' => &$finished,
        'message'  => &$task_message,
      );
      // Magic wrap to catch changes to 'message' key.
      $batch_context = new DrushBatchContext($batch_context);

      // Tolerate recoverable errors.
      // See https://github.com/drush-ops/drush/issues/1930
      $halt_on_error = drush_get_option('halt-on-error', TRUE);
      drush_set_option('halt-on-error', FALSE);
      call_user_func_array($function, array_merge($args, array(&$batch_context)));
      drush_set_option('halt-on-error', $halt_on_error);

      $finished = $batch_context['finished'];
      if ($finished >= 1) {
        // Make sure this step is not counted twice when computing $current.
        $finished = 0;
        // Remove the processed operation and clear the sandbox.
        $queue->deleteItem($item);
        $current_set['count']--;
        $current_set['sandbox'] = array();
      }
    }

    // When all operations in the current batch set are completed, browse
    // through the remaining sets, marking them 'successfully processed'
    // along the way, until we find a set that contains operations.
    // _batch_next_set() executes form submit handlers stored in 'control'
    // sets (see form_execute_handlers()), which can in turn add new sets to
    // the batch.
    $set_changed = FALSE;
    $old_set = $current_set;
    while (empty($current_set['count']) && ($current_set['success'] = TRUE) && _batch_next_set()) {
      $current_set = &_batch_current_set();
      $current_set['start'] = microtime(TRUE);
      $set_changed = TRUE;
    }

    // At this point, either $current_set contains operations that need to be
    // processed or all sets have been completed.
    $queue = _batch_queue($current_set);

    // If we are in progressive mode, break processing after 1 second.
    if (drush_memory_limit() > 0 && (memory_get_usage() * 2) >= drush_memory_limit()) {
      drush_log(dt("Batch process has consumed in excess of 50% of available memory. Starting new thread"), LogLevel::BATCH);
      // Record elapsed wall clock time.
      $current_set['elapsed'] = round((microtime(TRUE) - $current_set['start']) * 1000, 2);
      break;
    }
  }

  // Reporting 100% progress will cause the whole batch to be considered
  // processed. If processing was paused right after moving to a new set,
  // we have to use the info from the new (unprocessed) set.
  if ($set_changed && isset($current_set['queue'])) {
    // Processing will continue with a fresh batch set.
    $remaining        = $current_set['count'];
    $total            = $current_set['total'];
    $progress_message = $current_set['init_message'];
    $task_message     = '';
  }
  else {
    // Processing will continue with the current batch set.
    $remaining        = $old_set['count'];
    $total            = $old_set['total'];
    $progress_message = $old_set['progress_message'];
  }

  $current    = $total - $remaining + $finished;
  $percentage = _batch_api_percentage($total, $current);
  return ($percentage == 100);
}

/**
 * End the batch processing:
 * Call the 'finished' callbacks to allow custom handling of results,
 * and resolve page redirection.
 */
function _drush_batch_finished() {
  $batch = &batch_get();

  // Execute the 'finished' callbacks for each batch set, if defined.
  foreach ($batch['sets'] as $batch_set) {
    if (isset($batch_set['finished'])) {
      // Check if the set requires an additional file for function definitions.
      if (isset($batch_set['file']) && is_file($batch_set['file'])) {
        include_once DRUPAL_ROOT . '/' . $batch_set['file'];
      }
      if (is_callable($batch_set['finished'])) {
        $queue = _batch_queue($batch_set);
        $operations = $queue->getAllItems();
        $elapsed = $batch_set['elapsed'] / 1000;
        $elapsed = format_interval($elapsed);
        call_user_func_array($batch_set['finished'], [$batch_set['success'], $batch_set['results'], $operations, $elapsed]);
      }
    }
  }

  // Clean up the batch table and unset the static $batch variable.
  db_delete('batch')
    ->condition('bid', $batch['id'])
    ->execute();

  foreach ($batch['sets'] as $batch_set) {
    if ($queue = _batch_queue($batch_set)) {
      $queue->deleteQueue();
    }
  }
  $_batch = $batch;
  $batch = NULL;
  drush_set_option('drush_batch_process_finished', TRUE);
}

/**
 * Shutdown function: store the batch data for next request,
 * or clear the table if the batch is finished.
 */
function _drush_batch_shutdown() {
  if ($batch = batch_get()) {
    db_update('batch')
       ->fields(array('batch' => serialize($batch)))
       ->condition('bid', $batch['id'])
       ->execute();
  }
}
