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
  $items['unit-eval'] = array(
    'description' => 'Works like php-eval. Used for testing $command_specific context.',
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'callback' => 'drush_core_php_eval', // Note - no invoke hooks.
  );
  $items['unit-invoke'] = array(
    'description' => 'Return an array indicating which invoke hooks got called.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
  );
  return $items;
}

/*
 * Dynamically append custom bash code to the generated .bashrc.
 *
 * @see coreCase::testCoreCLI().
 */
function unit_cli_bashrc($drush_command, $interactive_mode) {
  return drush_get_option('unit-extra');
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