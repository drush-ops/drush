<?php
/**
 * @file
 *    Drush batch API.
 *
 * This file contains a fork of the Drupal Batch API that has been drastically
 * simplified and tailored to Drush's unique use case.
 *
 * The existing API is very targeted towards environments that are web accessible,
 * and would frequently attempt to redirect the user which would result in the
 * drush process being completely destroyed with no hope of recovery.
 *
 * While the original API does offer a 'non progressive' mode which simply
 * calls each operation in sequence within the current process, in most
 * implementations (D6), it would still attempt to redirect
 * unless very specific conditions were met.
 *
 * When operating in 'non progressive' mode, Drush would experience the problems
 * that the API was written to solve in the first place, specifically that processes
 * would exceed the available memory and exit with an error.
 *
 * Each major release of Drupal has also had slightly different implementations
 * of the batch API, and this provides a uniform interface to all of these
 * implementations.
 */

use Drush\Log\LogLevel;

/**
 * Class extending ArrayObject to allow the batch API to perform logging when
 * some keys of the array change.
 *
 * It is used to wrap batch's $context array and set log messages when values
 * are assigned to keys 'message' or 'error_message'.
 *
 * @see _drush_batch_worker().
 */
class DrushBatchContext extends ArrayObject {
  function offsetSet($name, $value) {
    if ($name == 'message') {
      drush_log(strip_tags($value), LogLevel::OK);
    }
    elseif ($name == 'error_message') {
      drush_set_error('DRUSH_BATCH_ERROR', strip_tags($value));
    }
    parent::offsetSet($name, $value);
  }
}

/**
 * Process a Drupal batch by spawning multiple Drush processes.
 *
 * This function will include the correct batch engine for the current
 * major version of Drupal, and will make use of the drush_backend_invoke
 * system to spawn multiple worker threads to handle the processing of
 * the current batch, while keeping track of available memory.
 *
 * The batch system will process as many batch sets as possible until
 * the entire batch has been completed or half of the available memory
 * has been used.
 *
 * This function is a drop in replacement for the existing batch_process()
 * function of Drupal.
 *
 * @param string $command
 *   (optional) The command to call for the back end process. By default this will be
 *   the 'batch-process' command, but some commands will
 *   have special initialization requirements, and will need to define and
 *   use their own command.
 * @param array $args
 *   (optional)
 * @param array $options
 *   (optional)
 */
function drush_backend_batch_process($command = 'batch-process', $args = [], $options = []) {
  // Command line options to pass to the command.
  $options['u'] = \Drupal::currentUser()->id();
  return _drush_backend_batch_process($command, $args, $options);
}

/**
 * Process sets from the specified batch.
 *
 * This function is called by the worker process that is spawned by the
 * drush_backend_batch_process function.
 *
 * The command called needs to call this function after it's special bootstrap
 * requirements have been taken care of.
 *
 * @param int $id
 *   The batch ID of the batch being processed.
 */
function drush_batch_command($id) {
  include_once(DRUSH_DRUPAL_CORE . '/includes/batch.inc');
  return _drush_batch_command($id);
}

/**
 * Main loop for the Drush batch API.
 *
 * Saves a record of the batch into the database, and progressively call $command to
 * process the operations.
 *
 * @param command
 *    The command to call to process the batch.
 *
 */
function _drush_backend_batch_process($command = 'batch-process', $args, $options) {
  $result = NULL;

  $batch =& batch_get();

  if (isset($batch)) {
    $process_info = [
      'current_set' => 0,
    ];
    $batch += $process_info;

    // The batch is now completely built. Allow other modules to make changes
    // to the batch so that it is easier to reuse batch processes in other
    // enviroments.
    \Drupal::moduleHandler()->alter('batch', $batch);

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

    // Store the batch.
    /** @var \Drupal\Core\Batch\BatchStorage $batch_storage */
    $batch_storage = \Drupal::service('batch.storage');
    $batch_storage->create($batch);
    $finished = FALSE;

    while (!$finished) {
      $result = drush_invoke_process('@self', $command, $args);
      $finished = drush_get_error() || !$result || (isset($result['context']['drush_batch_process_finished']) && $result['context']['drush_batch_process_finished'] == TRUE);
    }
  }

  return $result;
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

  $data = db_query("SELECT batch FROM {batch} WHERE bid = :bid", [
    ':bid' => $id
  ])->fetchField();

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
    return _drush_batch_finished();
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
      $batch_context = [
        'sandbox'  => &$current_set['sandbox'],
        'results'  => &$current_set['results'],
        'finished' => &$finished,
        'message'  => &$task_message,
      ];
      // Magic wrap to catch changes to 'message' key.
      $batch_context = new DrushBatchContext($batch_context);

      // Tolerate recoverable errors.
      // See https://github.com/drush-ops/drush/issues/1930
      $halt_on_error = \Drush\Drush::config()->get('runtime.php.halt-on-error', TRUE);
      \Drush\Drush::config()->set('runtime.php.halt-on-error', FALSE);
      $message = call_user_func_array($function, array_merge($args, [&$batch_context]));
      if (!empty($message)) {
        drush_print(strip_tags($message), 2);
      }
      \Drush\Drush::config()->set('runtime.php.halt-on-error', $halt_on_error);

      $finished = $batch_context['finished'];
      if ($finished >= 1) {
        // Make sure this step is not counted twice when computing $current.
        $finished = 0;
        // Remove the processed operation and clear the sandbox.
        $queue->deleteItem($item);
        $current_set['count']--;
        $current_set['sandbox'] = [];
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
  $results = [];

  $batch = &batch_get();

  // Execute the 'finished' callbacks for each batch set, if defined.
  foreach ($batch['sets'] as $id => $batch_set) {
    if (isset($batch_set['finished'])) {
      // Check if the set requires an additional file for function definitions.
      if (isset($batch_set['file']) && is_file($batch_set['file'])) {
        include_once DRUPAL_ROOT . '/' . $batch_set['file'];
      }
      if (is_callable($batch_set['finished'])) {
        $queue = _batch_queue($batch_set);
        $operations = $queue->getAllItems();
        $elapsed = $batch_set['elapsed'] / 1000;
        $elapsed = drush_drupal_major_version() >=8 ? \Drupal::service('date.formatter')->formatInterval($elapsed) : format_interval($elapsed);
        $batch_set['finished']($batch_set['success'], $batch_set['results'], $operations, $elapsed);
        $results[$id] = $batch_set['results'];
      }
    }
  }

  // Clean up the batch table and unset the static $batch variable.
  if (drush_drupal_major_version() >= 8) {
    /** @var \Drupal\Core\Batch\BatchStorage $batch_storage */
    $batch_storage = \Drupal::service('batch.storage');
    $batch_storage->delete($batch['id']);
  }
  else {
    db_delete('batch')
      ->condition('bid', $batch['id'])
      ->execute();
  }

  foreach ($batch['sets'] as $batch_set) {
    if ($queue = _batch_queue($batch_set)) {
      $queue->deleteQueue();
    }
  }
  $_batch = $batch;
  $batch = NULL;
  drush_set_option('drush_batch_process_finished', TRUE);

  return $results;
}

/**
 * Shutdown function: store the batch data for next request,
 * or clear the table if the batch is finished.
 */
function _drush_batch_shutdown() {
  if ($batch = batch_get()) {
    if (drush_drupal_major_version() >= 8) {
      /** @var \Drupal\Core\Batch\BatchStorage $batch_storage */
      $batch_storage = \Drupal::service('batch.storage');
      $batch_storage->update($batch);
    }
    else {
      db_update('batch')
        ->fields(['batch' => serialize($batch)])
        ->condition('bid', $batch['id'])
        ->execute();
    }
  }
}
