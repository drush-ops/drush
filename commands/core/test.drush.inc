<?php

/**
 * @file
 *   Simpletest module drush integration.
 */

/**
 * Implementation of hook_drush_command().
 */
function test_drush_command() {
  $items = array();

  $items['test-run'] = array(
    'description' => "Run tests. Note that you must use the --uri option.",
    'arguments' => array(
      'targets' => 'A test class, a test group. If omitted, a list of test classes and test groups is presented. Delimit multiple targets using commas.',
    ),
    'examples' => array(
      'test-run' => 'List all available classes and groups.',
      'sudo -u apache test-run --all' => 'Run all available tests. Avoid permission related failures by running as web server user.',
      'test-run XMLRPCBasicTestCase' => 'Run one test class.',
      'test-run XML-RPC' => 'Run all classes in a XML-RPC group.',
      'test-run XML-RPC,Filter' => 'Run all tests from multiple groups/classes.',
      'test-run XMLRPCBasicTestCase --methods="testListMethods, testInvalidMessageParsing"' => 'Run particular methods in the specified class or group.',
    ),
    'options' => array(
      'all' => 'Run all available tests',
      'methods' => 'A comma delimited list of methods that should be run within the test class. Defaults to all methods.',
      'dirty' => 'Skip cleanup of temporary tables and files. Helpful for reading debug() messages and other post-mortem forensics.',
      'xml' => 'Output verbose test results to a specified directory using the JUnit test reporting format. Useful for integrating with Jenkins.'

    ),
    'drupal dependencies' => array('simpletest'),
    // If you DRUSH_BOOTSTRAP_DRUPAL_LOGIN, you fall victim to http://drupal.org/node/974768. We'd like
    // to not bootstrap at all but simpletest uses Drupal to discover test classes,
    // cache the lists of tests, file_prepare_directory(), variable lookup like
    // httpauth creds, copy pre-built registry table from testing side, etc.
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  );
  $items['test-clean'] = array(
    'description' => "Clean temporary tables and files.",
    'drupal dependencies' => array('simpletest'),
  );

  return $items;
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of test classes and groups.
 */
function test_test_run_complete() {
  if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_FULL) == DRUSH_BOOTSTRAP_DRUPAL_FULL && module_exists('simpletest')) {
    // Retrieve all tests and groups.
    list($groups, $all_tests) = drush_test_get_all_tests();
    return array('values' => array_keys($groups) + $all_tests);
  }
}

// Command callback
function drush_test_clean() {
  return simpletest_clean_environment();
}

// Validate hook
function drush_test_run_validate($specs = NULL) {
  if (!drush_get_option('uri')) {
    // No longer needed?
    // return drush_set_error(dt("You must specify this site's URL using the --uri parameter."));
  }
}

/**
 * Test-run command callback.
 *
 * @specs
 *   A comman delimited string of test classes or group names.
 */
function drush_test_run($specs = NULL) {
  if (drush_drupal_major_version() > 7) {
    cache()->delete('simpletest');
  }
  else {
    cache_clear_all('simpletest', 'cache');
  }

  // Retrieve all tests and groups.
  list($groups, $all_tests) = drush_test_get_all_tests();

  if (drush_get_option('all')) {
    // Run all tests.
    foreach (array_keys($groups) as $group) {
      foreach (array_keys($groups[$group]) as $class) {
        drush_invoke_process('@self', 'test-run', array($class));
      }
    }
    return;
  }
  elseif (empty($specs)) {
    // No argument so list all groups/classes.
    return drush_test_list($groups);
  }
  elseif (strpos($specs, ',') === FALSE && in_array($specs, $all_tests)) {
    // We got one, valid class. Good, now run it.
    simpletest_drush_run_test($specs);
    if (!drush_get_option('dirty')) {
      simpletest_clean_environment();
    }
    return;
  }

  // See if we got a list of specs.
  foreach (explode(',', $specs) as $spec) {
    $spec = trim($spec);
    if (in_array($spec, $all_tests)) {
      // Specific test class specified.
      drush_test_run_class($spec);
    }
    if (isset($groups[$spec])) {
      // Specific group specified.
      foreach (array_keys($groups[$spec]) as $class) {
        drush_test_run_class($class);
      }
    }
  }
}

/*
 * Run a test class via drush_invoke_process()
 * @param string $class
 *   The test class to run.
 *
 * @return
 *   If the command could not be completed successfully, FALSE.
 *   If the command was completed, this will return an associative
 *   array containing the results of the API call.
 *   @see drush_backend_get_result()
 */
function drush_test_run_class($class) {
  $backend_options = array('output-label' => "$class ", 'integrate' => TRUE, 'output' => TRUE);
  $return = drush_invoke_process('@self', 'test-run', array($class), drush_redispatch_get_options(), $backend_options);
  return $return;
}

/**
 * Run a single test and display any failure messages.
 */
function simpletest_drush_run_test($class) {
  if (drush_drupal_major_version() >= 7) {
    $test_id = db_insert('simpletest_test_id')
    ->useDefaults(array('test_id'))
    ->execute();
  }
  else {
    db_query('INSERT INTO {simpletest_test_id} (test_id) VALUES (default)');
    $test_id = db_last_insert_id('simpletest_test_id', 'test_id');
  }
  $test = new $class($test_id);

  if ($methods_string = drush_get_option('methods')) {
    foreach (explode(',', $methods_string) as $method) {
      $methods[] = trim($method);
    }
    $test->run($methods);
  }
  else {
    $test->run();
  }

  $info = $test->getInfo();
  $status = ((isset($test->results['#fail']) && $test->results['#fail'] > 0)
          || (isset($test->results['#exception']) && $test->results['#exception'] > 0) ? 'error' : 'ok');
  drush_log($info['name'] . ' ' . _simpletest_format_summary_line($test->results), $status);

  if ($dir = drush_get_option('xml')) {
    drush_test_xml_results($test_id, $dir);
  }

  // If there were some failed tests show them.
  if ($status === 'error') {
    if (drush_drupal_major_version() >= 7) {
      $args = array(':test_id' => $test_id);
      $result = db_query("SELECT * FROM {simpletest} WHERE test_id = :test_id AND status IN ('exception', 'fail') ORDER BY test_class, message_id", $args);
      foreach($result as $record) {
        drush_set_error('DRUSH_TEST_FAIL', dt("Test !function failed: !message", array('!function' => $record->function, '!message' => $record->message)));
      }
    }
    else {
      $result = db_query("SELECT * FROM {simpletest} WHERE test_id = %d AND status IN ('exception', 'fail') ORDER BY test_class, message_id", $test_id);
      while ($row = db_fetch_object($result)) {
        drush_set_error('DRUSH_TEST_FAIL', dt("Test !function failed: !message", array('!function' => $row->function, '!message' => $row->message)));
      }
    }
  }
}

/**
 * Retrieve all test groups and sanitize their names to make them command-line
 * friendly.
 */
function simpletest_drush_test_groups($tests) {
  $groups = array();
  foreach (simpletest_categorize_tests($tests) as $name => $group) {
    $sanitized = strtr($name, array(' ' => ''));
    $groups[$sanitized] = $group;
  }
  return $groups;
}

// Print a listing of all available tests
function drush_test_list($groups) {
  $rows[] = array(dt('Command'), dt('Description'));
  $rows[] = array('-------', '-----------');
  foreach ($groups as $group_name => $group_tests) {
    foreach ($group_tests as $test_class => $test_info) {
      if (!isset($rows[$test_info['group']])) {
        $rows[$test_info['group']] = array($group_name, $test_info['group']);
      }
      $rows[] = array("  {$test_class}", "  {$test_info['name']}");
    }
  }
  return drush_print_table($rows, TRUE);
}

function drush_test_get_all_tests() {
  if (function_exists('simpletest_get_all_tests')) {
    $all_tests = simpletest_get_all_tests();
    $groups = simpletest_drush_test_groups($all_tests);
  }
  else {
    $groups = simpletest_test_get_all();
    $all_tests = array();
    foreach ($groups as $group) {
      $all_tests = array_merge($all_tests, array_keys($group));
    }
  }
  return array($groups, $all_tests);
}

/*
 * Display test results.
 */
function drush_test_xml_results($test_id, $dir) {
  $dir = is_string($dir) ? $dir : '.';

  $results_map = array(
    'pass' => 'Pass',
    'fail' => 'Fail',
    'exception' => 'Exception',
  );

  if (drush_drupal_major_version() >= 7) {
    $results = db_query("SELECT * FROM {simpletest} WHERE test_id = :test_id ORDER BY test_class, message_id", array(':test_id' => $test_id));
  }
  else {
    $result = db_query("SELECT * FROM {simpletest} WHERE test_id = %d ORDER BY test_class, message_id", $test_id);
    $results = array();
    while ($row = db_fetch_object($result)) {
      $results[] = $row;
    }
  }

  $test_class = '';
  $xml_files = array();

  foreach ($results as $result) {
    if (isset($results_map[$result->status])) {
      if ($result->test_class != $test_class) {
        // Display test class every time results are for new test class.
        if (isset($xml_files[$test_class])) {
          file_put_contents($dir . '/' . $test_class . '.xml', $xml_files[$test_class]['doc']->saveXML());
          unset($xml_files[$test_class]);
        }
        $test_class = $result->test_class;
        if (!isset($xml_files[$test_class])) {
          $doc = new DomDocument('1.0');
          $root = $doc->createElement('testsuite');
          $root = $doc->appendChild($root);
          $xml_files[$test_class] = array('doc' => $doc, 'suite' => $root);
        }
      }
      // Save the result into the XML:
      $case = $xml_files[$test_class]['doc']->createElement('testcase');
      $case->setAttribute('classname', $test_class);
      list($class, $name) = explode('->', $result->function, 2);
      $case->setAttribute('name', $name);

      if ($result->status == 'fail') {
        $fail = $xml_files[$test_class]['doc']->createElement('failure');
        $fail->setAttribute('type', 'failure');
        $fail->setAttribute('message', $result->message_group);
        $text = $xml_files[$test_class]['doc']->createTextNode($result->message);
        $fail->appendChild($text);
        $case->appendChild($fail);
      }

      $xml_files[$test_class]['suite']->appendChild($case);
    }
  }

  // Save the last one:
  if (isset($xml_files[$test_class])) {
    file_put_contents($dir . '/' . $test_class . '.xml', $xml_files[$test_class]['doc']->saveXML());
    unset($xml_files[$test_class]);
  }
}
