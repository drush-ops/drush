<?php
// $Id$

/*
 * @file
 *   Commands which are useful for unit tests.
 */

/**
 * Implementation of hook_drush_command().
 */
function unit_drush_command() {
  $items['unit'] = array(
    'description' => 'No-op command, used to test completion for commands that start the same as other commands.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
  );
  $items['unit-eval'] = array(
    'description' => 'Works like php-eval. Used for testing $command_specific context.',
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'callback' => 'drush_core_php_eval', // Note - no invoke hooks.
  );
  $items['unit-invoke'] = array(
    'description' => 'Return an array indicating which invoke hooks got called.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
  );
  $items['unit-batch'] = array(
    'description' => 'Run a batch process.',
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
  );
  return $items;
}

// Implement each invoke hook with the same single line of code.
// That line records that the hook was called.
function drush_unit_invoke_init() {unit_invoke_log(__FUNCTION__);}
function drush_unit_invoke_validate() {unit_invoke_log(__FUNCTION__);}
function drush_unit_pre_unit_invoke() {unit_invoke_log(__FUNCTION__);}
function drush_unit_invoke() {unit_invoke_log(__FUNCTION__);}
function drush_unit_pre_unit_invoke_rollback() {unit_invoke_log(__FUNCTION__);}
function drush_unit_post_unit_invoke_rollback() {unit_invoke_log(__FUNCTION__);}

// Record that hook_drush_init() fired.
function unit_drush_init() {
  $command = drush_get_command();
  if ($command['command'] == 'unit-invoke') {
    unit_invoke_log(__FUNCTION__);
  }
}

function drush_unit_post_unit_invoke() {
  // Record that this hook was called.
  unit_invoke_log(__FUNCTION__);

  // Make sure we enter into rollback.
  drush_set_error('');
}

/*
 * The final invoke hook. Emit the call history.
 * Cannot use 'exit' as it does not fire in rollback scenario.
 */
function drush_unit_invoke_validate_rollback() {
  unit_invoke_log(__FUNCTION__);
  print json_encode(unit_invoke_log());
}

function unit_invoke_log($function = NULL) {
  static $called = array();
  if ($function) {
    $called[] = $function;
  }
  else {
    return $called;
  }
}

/**
 * Command callback.
 */
function drush_unit_batch() {
  // Reduce php memory/time limits to test backend respawn.
  // TODO.

  $batch = array(
    'operations' => array(
       array('_drush_unit_batch_operation', array()),
    ),
    'finished' => '_drush_unit_batch_finished',
    // 'file' => Doesn't work for us. Drupal 7 enforces this path
    // to be relative to DRUPAL_ROOT.
    // @see _batch_process().
  );
  batch_set($batch);
  drush_backend_batch_process();

  // Print the batch output.
  drush_backend_output();
}

function _drush_unit_batch_operation(&$context) {
  $context['message'] = "!!! ArrayObject does its job.";

  for ($i = 0; $i < 5; $i++) {
    drush_print("Iteration $i");
  }
  $context['finished'] = 1;
}

function _drush_unit_batch_finished() {
  // Restore php limits.
  // TODO.
}
