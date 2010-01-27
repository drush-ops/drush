<?php
/**
 * @file
 *   Drupal 6 engine for the Batch API
 */

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
function _drush_backend_batch_process($command = 'batch-process') {
  $batch =& batch_get();

  if (isset($batch)) {
    $process_info = array(
      'current_set' => 0,
    );
    $batch += $process_info;

    // Initiate db storage in order to get a batch id. We have to provide
    // at least an empty string for the (not null) 'token' column.
    db_query("INSERT INTO {batch} (token, timestamp) VALUES ('', %d)", time());
    $batch['id'] = db_last_insert_id('batch', 'bid');

    // Actually store the batch data and the token generated form the batch id.
    db_query("UPDATE {batch} SET token = '%s', batch = '%s' WHERE bid = %d", drupal_get_token($batch['id']), serialize($batch), $batch['id']);

    $finished = FALSE;

    while (!$finished) {
      $data = drush_backend_invoke($command, array($batch['id']));

      $finished = drush_get_error() || !$data || ($data['context']['drush_batch_process_finished'] == TRUE);
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
  // Retrieve the current state of batch from db.
  if ($data = db_result(db_query("SELECT batch FROM {batch} WHERE bid = %d", $id))) {
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

  timer_start('batch_processing');

  while (!$current_set['success']) {
    // If this is the first time we iterate this batch set in the current
    // request, we check if it requires an additional file for functions
    // definitions.
    if ($set_changed && isset($current_set['file']) && is_file($current_set['file'])) {
      include_once($current_set['file']);
    }

    $finished = 1;
    $task_message = '';
    if ((list($function, $args) = reset($current_set['operations'])) && function_exists($function)) {
      // Build the 'context' array, execute the function call,
      // and retrieve the user message.
      $batch_context = array('sandbox' => &$current_set['sandbox'], 'results' => &$current_set['results'], 'finished' => &$finished, 'message' => &$task_message);
      // Process the current operation.
      call_user_func_array($function, array_merge($args, array(&$batch_context)));
    }

    if ($finished == 1) {
      // Make sure this step isn't counted double when computing $current.
      $finished = 0;
      // Remove the operation and clear the sandbox.
      array_shift($current_set['operations']);
      $current_set['sandbox'] = array();
    }

    // If the batch set is completed, browse through the remaining sets,
    // executing 'control sets' (stored form submit handlers) along the way -
    // this might in turn insert new batch sets.
    // Stop when we find a set that actually has operations.
    $set_changed = FALSE;
    $old_set = $current_set;
    while (empty($current_set['operations']) && ($current_set['success'] = TRUE) && _batch_next_set()) {
      $current_set =& _batch_current_set();
      $set_changed = TRUE;
    }
    // At this point, either $current_set is a 'real' batch set (has operations),
    // or all sets have been completed.


    // TODO - replace with memory check!
    // If we're in progressive mode, stop after 1 second.
    if ((memory_get_usage() * 2) >= drush_memory_limit()) {
      drush_log(dt("Batch process has consumed in excess of 50% of available memory. Starting new thread"), "batch");
      break;
    }
  }

  // Gather progress information.

  // Reporting 100% progress will cause the whole batch to be considered
  // processed. If processing was paused right after moving to a new set,
  // we have to use the info from the new (unprocessed) one.
  if ($set_changed && isset($current_set['operations'])) {
    // Processing will continue with a fresh batch set.
    $remaining = count($current_set['operations']);
    $total = $current_set['total'];
    $task_message = '';
  }
  else {
    $remaining = count($old_set['operations']);
    $total = $old_set['total'];
  }

  $current    = $total - $remaining + $finished;
  $percentage = $total ? floor($current / $total * 100) : 100;

  return ($percentage == 100);
}

/**
 * End the batch processing:
 * Call the 'finished' callbacks to allow custom handling of results,
 * and resolve page redirection.
 */
function _drush_batch_finished() {
  $batch =& batch_get();

  // Execute the 'finished' callbacks for each batch set.
  foreach ($batch['sets'] as $key => $batch_set) {
    if (isset($batch_set['finished'])) {
      // Check if the set requires an additional file for functions definitions.
      if (isset($batch_set['file']) && is_file($batch_set['file'])) {
        include_once($batch_set['file']);
      }
      if (function_exists($batch_set['finished'])) {
        $batch_set['finished']($batch_set['success'], $batch_set['results'], $batch_set['operations']);
      }
    }
  }

  // Cleanup the batch table and unset the global $batch variable.
  db_query("DELETE FROM {batch} WHERE bid = %d", $batch['id']);
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
    db_query("UPDATE {batch} SET batch = '%s' WHERE bid = %d", serialize($batch), $batch['id']);
  }
}
