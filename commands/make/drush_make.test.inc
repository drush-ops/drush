<?php

/**
 * Main drush make-test callback.
 */
function drush_make_test($test) {
  if (empty($test)) {
    $rows = array();
    $rows[] = array(dt('Command'), dt('Description'));
    $rows[] = array('-------', '-----------');
    foreach (drush_make_test_get_tests() as $id => $info) {
      $rows[] = array($id, $info['name']);
    }
    drush_print_table($rows, TRUE);
  }
  else if ($test === 'ALL') {
    foreach (array_keys(drush_make_test_get_tests()) as $id) {
      drush_make_test_run_test($id);
    }
  }
  else {
    drush_make_test_run_test($test);
  }
}

/**
 * Get an array of all tests.
 */
function drush_make_test_get_tests($id = NULL) {
  $tests = array(
    'cvs' => array(
      'name'     => 'CVS integration',
      'makefile' => 'tests/cvs.make',
      'md5'      => 'f69d29c7ed065b42290bafb7ab9439f1',
      'options'  => array(),
    ),
    'no-patch-txt' => array(
      'name'     => 'Test --no-patch-txt option',
      'makefile' => 'tests/patches.make',
      'md5'      => '24f14f6e28498678cebcab2cff3e1030',
      'options'  => array('no-core' => TRUE, 'no-patch-txt' => TRUE),
    ),
    'patch' => array(
      'name'     => 'Test patching and writing of PATCHES.txt file',
      'makefile' => 'tests/patches.make',
      'md5'      => '173ae5e2120463e31ff2e2a9cc02491b',
      'options'  => array('no-core' => TRUE),
    ),
  );
  if (isset($id)) {
    return isset($tests[$id]) ? $tests[$id] : FALSE;
  }
  return $tests;
}

/**
 * Run a drush make test.
 */
function drush_make_test_run_test($id) {
  if ($test = drush_make_test_get_tests($id)) {
    $success = FALSE;
    $makefile = dirname(__FILE__) .'/'. $test['makefile'];
    $options = $test['options'] + array('test' => TRUE, 'md5' => TRUE);
    if ($data = drush_backend_invoke('make '. $makefile .' test', $options)) {
      foreach ($data['log'] as $item) {
        if ($item['message'] === "Build hash: {$test['md5']}") {
          drush_log(dt('Drush make test @testname passed.', array('@testname' => $test['name'])), 'ok');
          return TRUE;
        }
      }
    }
    drush_log(dt('Drush make test @testname failed.', array('@testname' => $test['name'])), 'error');
    return FALSE;
  }
  drush_log(dt('Test @testname could not be found.', array('@testname' => $test['name'])), 'error');
  return FALSE;
}
