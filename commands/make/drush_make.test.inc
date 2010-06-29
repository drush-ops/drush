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
    'get-post' => array(
      'name'     => 'Test GET and POST retrieval of projects',
      'makefile' => 'tests/getpost.make',
      'md5'      => 'fdc1d0892317fb8aa6c16d7684e366cc',
      'options'  => array('no-core' => TRUE),
    ),
    'git' => array(
      'name'     => 'GIT integration',
      'makefile' => 'tests/git.make',
      'md5'      => 'e5210b009b52d60d64ce5effd7e6cf83',
      'options'  => array('no-core' => TRUE),
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
    'include' => array(
      'name'     => 'Including files',
      'makefile' => 'tests/include.make',
      'md5'      => 'a20c3f4f4167c6577156fa616f542120',
      'options'  => array(),
    ),
    'recursion' => array(
      'name'     => 'Recursion',
      'makefile' => 'tests/recursion.make',
      'md5'      => 'b4d0599c7c44566a7e3dc9086b870723',
      'options'  => array('no-core' => TRUE),
    ),
    'svn' => array(
      'name'     => 'SVN',
      'makefile' => 'tests/svn.make',
      'md5'      => '0cb28a15958d7fc4bbf8bf6b00bc6514',
      'options'  => array('no-core' => TRUE),
    ),
    'bzr' => array(
      'name'     => 'Bzr',
      'makefile' => 'tests/bzr.make',
      'md5'      => '272e2b9bb27794c54396f2f03c159725',
      'options'  => array(),
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

    // Log the test command.
    $debug_command = 'drush make';
    foreach ($options as $k => $v) {
      $debug_command .= _drush_escape_option($k, $v);
    }
    $debug_command .= " {$makefile}";
    drush_log(dt('Test command: @debug.', array('@debug' => $debug_command)), 'ok');

    if ($data = drush_backend_invoke('make '. $makefile, $options)) {
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
