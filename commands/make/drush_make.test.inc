<?php
// $Id$

/**
 * Main drush make-test callback.
 */
function drush_make_test($test) {
  if (empty($test)) {
    $rows = array();
    $rows[] = array(dt('Command'), dt('Description'));
    $rows[] = array(   '-------',     '-----------');
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
      'build'    => TRUE,
      'messages' => array(
        'Build hash: f69d29c7ed065b42290bafb7ab9439f1',
      ),
      'options'  => array(),
    ),
    'get' => array(
      'name'     => 'Test GET retrieval of projects',
      'makefile' => 'tests/get.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: 4bf18507da89bed601548210c22a3bed',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'post' => array(
      'name'     => 'Test POST retrieval of projects',
      'makefile' => 'tests/post.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: df331bdead0bfa3a040279e3dbfc1c3f',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'git' => array(
      'name'     => 'GIT integration',
      'makefile' => 'tests/git.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: e5210b009b52d60d64ce5effd7e6cf83',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'no-patch-txt' => array(
      'name'     => 'Test --no-patch-txt option',
      'makefile' => 'tests/patches.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: 24f14f6e28498678cebcab2cff3e1030',
      ),
      'options'  => array('no-core' => TRUE, 'no-patch-txt' => TRUE),
    ),
    'patch' => array(
      'name'     => 'Test patching and writing of PATCHES.txt file',
      'makefile' => 'tests/patches.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: 173ae5e2120463e31ff2e2a9cc02491b',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'include' => array(
      'name'     => 'Including files and property overrides',
      'makefile' => 'tests/include.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: e2e230ec5eccaf5618050559ab11510d',
      ),
      'options'  => array(),
    ),
    'recursion' => array(
      'name'     => 'Recursion',
      'makefile' => 'tests/recursion.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: b4d0599c7c44566a7e3dc9086b870723',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'svn' => array(
      'name'     => 'SVN',
      'makefile' => 'tests/svn.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: 0cb28a15958d7fc4bbf8bf6b00bc6514',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'bzr' => array(
      'name'     => 'Bzr',
      'makefile' => 'tests/bzr.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: 272e2b9bb27794c54396f2f03c159725',
      ),
      'options'  => array(),
    ),
    'translations' => array(
      'name'     => 'Translation downloads',
      'makefile' => 'tests/translations.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: df10742a96cabd9f839add33e3251b04',
      ),
      'options'  => array('translations' => 'es,pt-br'),
    ),
    'contrib-destination' => array(
      'name'     => 'Contrib-destination attribute',
      'makefile' => 'tests/contrib-destination.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: d615d004adfa8ebfe44e91119b88389c',
      ),
      'options'  => array('no-core' => TRUE, 'contrib-destination' => '.'),
    ),
    'file' => array(
      'name'     => 'File extraction',
      'makefile' => 'tests/file.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: ab8cb79508e9c992dace2e40c6435e5b',
      ),
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
    $makefile = dirname(__FILE__) .'/'. $test['makefile'];
    $options = $test['options'] + array('test' => TRUE, 'md5' => TRUE);

    // Log the test command.
    $debug_command = 'drush make';
    foreach ($options as $k => $v) {
      $debug_command .= _drush_escape_option($k, $v);
    }
    $debug_command .= " {$makefile}";
    drush_log(dt('Test command: @debug.', array('@debug' => $debug_command)), 'ok');

    $fail = FALSE;
    if ($data = drush_backend_invoke('make '. $makefile, $options)) {
      // Test build completion.
      if (isset($test['build'])) {
        if ($test['build'] && !empty($data['error_status'])) {
          $fail = TRUE;
          drush_log(dt('@testname: build failed.', array('@testname' => $test['name'])), 'error');
        }
        elseif (!$test['build'] && empty($data['error_status'])) {
          $fail = TRUE;
          drush_log(dt('@testname: build completed.', array('@testname' => $test['name'])), 'error');
        }
      }
      // Test for messages.
      if (isset($test['messages'])) {
        $messages = array();
        foreach ($data['log'] as $item) {
          $messages[] = $item['message'];
        }
        foreach ($test['messages'] as $message) {
          if (!in_array($message, $messages)) {
            $fail = TRUE;
            drush_log(dt('@testname: "@message" not found.', array('@testname' => $test['name'], '@message' => $message)), 'error');
          }
        }
      }
      if (!$fail) {
        drush_log(dt('Drush make test @testname passed.', array('@testname' => $test['name'])), 'ok');
        return TRUE;
      }
    }
    drush_log(dt('Drush make test @testname failed.', array('@testname' => $test['name'])), 'error');
    return FALSE;
  }
  drush_log(dt('Test @testname could not be found.', array('@testname' => $test['name'])), 'error');
  return FALSE;
}
