<?php

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
        'Build hash: 9a5e2476d858ff182053257a688e32f0',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'git' => array(
      'name'     => 'GIT integration',
      'makefile' => 'tests/git.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: 374b448ab58cb329585ff75337bf5374',
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
        'Build hash: 4aa84d1d71fbe1120cb2988cc4dd224d',
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
        'Build hash: f76ec174a775ce67f8e9edcb02336ef2',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'md5-succeed' => array(
      'name'     => 'MD5 validation',
      'makefile' => 'tests/md5-succeed.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: f76ec174a775ce67f8e9edcb02336ef2',
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'md5-fail' => array(
      'name'     => 'Failed MD5 validation test',
      'makefile' => 'tests/md5-fail.make',
      'build'    => FALSE,
      'messages' => array(
        'Checksum md5 verification failed for README.txt. Expected - fail -, received c8968d801a953b9ea735364d6f3dfabc.'
      ),
      'options'  => array('no-core' => TRUE),
    ),
    'ignore-checksums' => array(
      'name'     => 'Ignore invalid checksum/s',
      'makefile' => 'tests/md5-fail.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: f76ec174a775ce67f8e9edcb02336ef2',
      ),
      'options'  => array('no-core' => TRUE, 'ignore-checksums' => TRUE),
    ),
    'do-fail-attribute' => array(
      'name'     => 'D.o: Fail attributes',
      'makefile' => 'tests/do-fail-attribute.make',
      'build'    => FALSE,
      'options'  => array('drupal-org' => TRUE),
    ),
    'do-fail-dev' => array(
      'name'     => 'D.o: Fail dev',
      'makefile' => 'tests/do-fail-dev.make',
      'build'    => FALSE,
      'options'  => array('drupal-org' => TRUE),
    ),
    'do-fail-library' => array(
      'name'     => 'D.o: Fail library',
      'makefile' => 'tests/do-fail-library.make',
      'build'    => FALSE,
      'options'  => array('drupal-org' => TRUE),
    ),
    'do-fail-patch' => array(
      'name'     => 'D.o: Fail patch',
      'makefile' => 'tests/do-fail-patch.make',
      'build'    => FALSE,
      'options'  => array('drupal-org' => TRUE),
    ),
    'do-fail-version' => array(
      'name'     => 'D.o: Fail version',
      'makefile' => 'tests/do-fail-version.make',
      'build'    => FALSE,
      'options'  => array('drupal-org' => TRUE),
    ),
    'do-succeed' => array(
      'name'     => 'D.o: Success',
      'makefile' => 'tests/do-succeed.make',
      'build'    => TRUE,
      'messages' => array(
        'Build hash: fc3cedb0f656a4d9bc071815e4ca2e07',
      ),
      'options'  => array('drupal-org' => TRUE),
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
        elseif (!$test['build']) {
          $error_code =& drush_get_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
          $error_code = DRUSH_SUCCESS;
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
