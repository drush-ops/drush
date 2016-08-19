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
 *   the 'batch-process' command, but some commands such as updatedb will
 *   have special initialization requirements, and will need to define and
 *   use their own command.
 * @param array $args
 *   (optional)
 * @param array $options
 *   (optional)
 */
function drush_backend_batch_process($command = 'batch-process', $args = array(), $options = array()) {
  // Command line options to pass to the command.
  $options['u'] = drush_user_get_class()->getCurrentUserAsSingle()->id();

  drush_include_engine('drupal', 'batch');
  _drush_backend_batch_process($command, $args, $options);
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
  drush_include_engine('drupal', 'batch');
  _drush_batch_command($id);
}
