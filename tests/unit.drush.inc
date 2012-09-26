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
  $items['unit-return-options'] = array(
    'description' => 'Return options as function result.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
  );
  $items['unit-return-argv'] = array(
    'description' => 'Return original argv as function result.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
  );
  $items['missing-callback'] = array(
    'description' => 'Command with no callback function, to test error reporting.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
  );
  $items['unit-drush-dependency'] = array(
    'description' => 'Command depending on an unknown commandfile.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'drush dependencies' => array('unknown-commandfile'),
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

// Return all of the option values passed in to this routine, minus the
// global options.
function drush_unit_return_options() {
  $all_option_values = array_merge(drush_get_context('cli'), drush_get_context('stdin'));
  foreach (drush_get_global_options() as $key => $info) {
    unset($all_option_values[$key]);
  }
  return $all_option_values;
}

// Return all of the original arguments passed to this script
function drush_unit_return_argv() {
  return drush_get_context('argv');
}
