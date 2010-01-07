<?php
// $Id$

/**
 * @file Drush simpletest commands
 */

/**
 * Implementation of hook_drush_help().
 */
function simpletest_drush_help($section) {
  switch ($section) {
    case 'drush:test-mail':
      return dt("Run tests and email the results. See the docs for run-tests.sh to understand --extra.");
  case 'drush:test-clean':
    return dt("Clean leftover tables and file directories from prior test runs.");
  }
}

/**
 * Implementation of hook_drush_command().
 */
function simpletest_drush_command() {
  $items['test-mail'] = array(
    'callback' => 'drush_test_mail',
    'description' => 'Run all tests and mail the results to your team.',
    'examples' => array(
       "drush test-mail me@example.com --extra=\"--class BlockTestCase\"" => "Run block tests & email to me.",
       "drush test-mail me@example.com --extra=\"--all\"" => "Run all tests & email to me.",
    ),
    'arguments' => array(
      'recipients' => 'A comma delimited list of email addrresses.',
    ),
    'options' => array(
      '--extra' => 'Additional parameters to be passed to run-tests.sh',
      '--php' => 'Full path to your PHP interpreter. Usually not needed.'
    ),
    'drupal dependencies' => array('simpletest'),
    'core' => array('6','7'),
  );
  $items['test-clean'] = array(
    'callback' => 'drush_test_clean',
    'description' => 'Delete leftover tables and files from prior test runs.',
    'drupal dependencies' => array('simpletest'),
    'core' => array('6','7'),
  );
  $items['test-drush'] = array(
    'callback' => 'drush_test_drush',
    'description' => 'Run drush-specific tests',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  return $items;
}

// A drush command callback.
function drush_test_clean() {
  simpletest_clean_environment();
  drush_print("Simpletest environment successfully cleaned.");
}


// A Drush command callback.
function drush_test_mail($recipients) {
  $run_tests = './scripts/run-tests.sh';
  if (!file_exists($run_tests)) {
    return drush_set_error('DRUSH_SIMPLETEST_RUNTESTS_SH', dt('You must copy or symlink run-tests.sh into your /scripts directory beneath Drupal root.'));
  }

  $extra = drush_get_option('extra');
  $url = escapeshellarg(url('', array('absolute' => TRUE)));
  $exec = "$run_tests --php '" . DRUSH_COMMAND . '\' --url ' . $url . " $extra";
  drush_shell_exec($exec);
  $output =  implode("\n", drush_shell_exec_output());
  $subject = 'Simpletest results - ' . drush_simpletest_format_results($output);
  mail($recipients, $subject, $output);
}

// Based on pifr_review_format_results(). Not working yet. Help wanted.
function drush_simpletest_format_results($output) {
  if (preg_match_all('/^(.*?) (\d+) (?:pass|passes), (\d+) fails?, and (\d+) exceptions?$/m', $output, $matches, PREG_SET_ORDER)) {
    $results = array('pass' => 0, 'fail' => 0, 'exception' => 0);
    foreach ($matches as $match) {
      // Add to results count array.
      $results['pass'] += $match[2];
      $results['fail'] += $match[3];
      $results['exception'] += $match[4];
    }
    return $results['pass']  . ' pass, ' . $results['fail'] . ' failures, ' . $results['exceptions'] . '  exceptions';
  }
  else {
    return dt('Unknown.');
  }
}

/**
 * Simple drush self-test procedure
 *
 * This only tests self-execution for now.
 *
 * XXX: this needs to be adapted to a testing framework, see:
 *
 * http://drupal.org/node/483940
 */
function drush_test_drush() {
  drush_log(dt("Invoking %drush help in a subprocess", array('%drush' => DRUSH_COMMAND)));
  drush_backend_invoke('help', array(), 'GET', FALSE);
}