<?php

/**
 * @file
 *   Simpletest module drush integration.
 */

/**
 * Implements hook_drush_help_alter().
 */
function test_drush_help_alter(&$command) {
  if (drush_drupal_major_version() >= 8) {
    if ($command['command'] == 'test-run') {
      foreach ($command['examples'] as $key => $value) {
        $newkey = str_replace('BootstrapPageCacheTestCase', 'Drupal\system\Tests\Bootstrap\PageCacheTest', $key);
        if ($newkey != $key) {
          $command['examples'][$newkey] = $value;
          unset($command['examples'][$key]);
        }
      }
    }
  }
}

/**
 * Implementation of hook_drush_command().
 */
function test_drush_command() {
  $items = array();

  $items['test-run'] = array(
    'description' => "Run tests. Note that you must use the --uri option via the CLI or via a site alias.",
    'arguments' => array(
      'targets' => 'A test class, a test group. If omitted, a list of test classes and test groups is presented. Delimit multiple targets using commas.',
    ),
    'examples' => array(
      'test-run' => 'List all available classes and groups.',
      'sudo -u apache test-run --all' => 'Run all available tests. Avoid permission related failures by running as web server user.',
      'test-run BootstrapPageCacheTestCase' => 'Run one test class.',
      'test-run Bootstrap' => 'Run all classes in a XML-RPC group.',
      'test-run Bootstrap,Filter' => 'Run all tests from multiple groups/classes.',
      'test-run BootstrapPageCacheTestCase --methods="testPageCompression, testPageCache"' => 'Run particular methods in the specified class or group.',
    ),
    'options' => array(
      'all' => 'Run all available tests',
      'methods' => 'A comma delimited list of methods that should be run within the test class. Defaults to all methods.',
      'dirty' => 'Skip cleanup of temporary tables and files. Helpful for reading debug() messages and other post-mortem forensics.',
      'xml' => 'Output verbose test results to a specified directory using the JUnit test reporting format. Useful for integrating with Jenkins.',
      'artifacts' => 'Output to a specific folder the test results in a human-viewable, themed output, including the verbose messages and required CSS for rendering. Useful for saving as an artifact for future viewing in Jenkins.',
    ),
    'outputformat' => array(
      'default' => 'table',
      'field-labels' => array(
        'group' => 'Group',
        'class' => 'Class',
        'name' => 'Name',
      ),
      'output-data-type' => 'format-table',
    ),
    // If you DRUSH_BOOTSTRAP_DRUPAL_LOGIN, you fall victim to http://drupal.org/node/974768. We'd like
    // to not bootstrap at all but simpletest uses Drupal to discover test classes,
    // cache the lists of tests, file_prepare_directory(), variable lookup like
    // httpauth creds, copy pre-built registry table from testing side, etc.
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'drupal dependencies' => array('simpletest'),
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
 *   A comma delimited string of test classes or group names.
 */
function drush_test_run($specs = NULL) {
  if (drush_drupal_major_version() > 7) {
    // \Drupal::cache('simpletest')->delete();
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
    return drush_get_error() === DRUSH_SUCCESS;
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

/**
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
 *
 * @param $class
 *   A test class name
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

  $test_suites = drush_test_get_results($test_id, $info);
  if ($dir = drush_get_option('xml')) {
    drush_test_xml_results($test_id, $test_suites, $dir);
  }
  if ($dir = drush_get_option('artifacts')) {
    drush_test_artifact_results($test_id, $test_suites, $dir, $info['name']);
  }
  if ($status === 'error') {
    if (drush_drupal_major_version() >= 7) {
      $args = array(':test_id' => $test_id);
      $result = db_query("SELECT * FROM {simpletest} WHERE test_id = :test_id AND status IN ('exception', 'fail') ORDER BY test_class, message_id", $args);
      foreach ($result as $record) {
        drush_set_error('DRUSH_TEST_FAIL', dt("Test !function failed: !message in !file on line !line", array(
          '!function' => $record->function,
          '!message' => $record->message,
          '!file' => $record->file,
          '!line' => $record->line,
        )));
      }
    }
    else {
      $result = db_query("SELECT * FROM {simpletest} WHERE test_id = %d AND status IN ('exception', 'fail') ORDER BY test_class, message_id", $test_id);
      while ($row = db_fetch_object($result)) {
        drush_set_error('DRUSH_TEST_FAIL', dt("Test !function failed: !message in !file on line !line", array(
          '!function' => $row->function,
          '!message' => $row->message,
          '!file' => $row->file,
          '!line' => $row->line,
        )));
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
  foreach ($groups as $group_name => $group_tests) {
    foreach ($group_tests as $test_class => $test_info) {
      $rows[] = array('group' => $group_name, 'class' => $test_class, 'name' => $test_info['name']);
    }
  }
  return $rows;
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
 * Compile the test results data structure
 */
function drush_test_get_results($test_id, $info) {
  // Get an array of test result objects from the database.
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

  // Collect and aggregate the data from simpletest.
  $test_suites = array();
  foreach ($results as $result) {
    // Create test_suite object.
    // Formatting name so it becomes "group.name" without any other dots. Jenkins uses string splitting on dots to gather info.
    $test_suite_name = str_replace('.', '', $info['group']) . '.' . str_replace('.', '', $info['name']);
    if (!isset($test_suites[$test_suite_name])) {
      $test_suite = new stdClass();
      $test_suite->name = $test_suite_name;
      $test_suite->test_cases = array();
      $test_suites[$test_suite_name] = $test_suite;
    }
    else {
      $test_suite = $test_suites[$test_suite_name];
    }
    // Create test_case object.
    list(, $test_case_name) = explode('->', $result->function, 2);
    if (empty($test_case_name)) {
      // There is no '->' present on static function calls. Use the whole string in those cases.
      $test_case_name = $result->function;
    }
    $test_case_name = str_replace('.', '', $test_case_name);  // Remove those dots Jenkins loves so much.
    if (!isset($test_suite->test_cases[$test_case_name])) {
      $test_case = new stdClass();
      $test_case->name = $test_case_name;
      $test_case->failure_message = '';
      $test_case->error_message = '';
      $test_case->system_out = '';
      $test_case->system_err = '';
      $test_suite->test_cases[$test_case_name] = $test_case;
    }
    else {
      $test_case = $test_suite->test_cases[$test_case_name];
    }
    // Prepare message.
    $status = str_pad($result->status . ':', 10);
    $root = drush_get_option('root');
    if (substr($root, -1) !== '/') {
      $root .= '/';
    }
    $result->file = str_replace($root, '', $result->file);
    $message = strip_tags($result->message, '<a>');  // Jenkins encodes the output so don't use any tags.
    $message = preg_replace('/<a.*?href="([^"]+)".*?>(.*?)<\/a>/', '$1 $2', $message); // Jenkins will turn urls into clickable links.
    $message = $status . ' [' . $result->message_group . '] ' . $message . ' [' . $result->file . ':' . $result->line . "]\n";
    // Everything is logged in system_out.
    $test_case->system_out .= $message;
    // Failures go to failures.
    if ($result->status == 'fail') {
      $test_case->failure_message .= $message;
    }
    // Exceptions go both to errors and system_err.
    if ($result->status == 'exception') {
      $test_case->error_message .= $message;
      $test_case->system_err .= $message;
    }
  }
  return $test_suites;
}

/**
 * Write test results in jUnit XML format.
 */
function drush_test_xml_results($test_id, $test_suites, $dir) {
  $dir = is_string($dir) ? $dir : '.';
  // Build an XML document from our results.
  $xml = new DOMDocument('1.0', 'UTF-8');
  foreach ($test_suites as $test_suite) {
    $test_suite_element = $xml->createElement('testsuite');
    $test_suite_element->setAttribute('name', $test_suite->name);
    foreach ($test_suite->test_cases as $test_case) {
      $test_case_element = $xml->createElement('testcase');
      $test_case_element->setAttribute('name', $test_case->name);
      if (!empty($test_case->failure_message)) {
        $failure_element = $xml->createElement('failure');
        $failure_element->setAttribute('message', $test_case->failure_message);
        $test_case_element->appendChild($failure_element);
      }
      if (!empty($test_case->error_message)) {
        $error_element = $xml->createElement('error');
        $error_element->setAttribute('message', $test_case->error_message);
        $test_case_element->appendChild($error_element);
      }
      if (!empty($test_case->system_out)) {
        $system_out_element = $xml->createElement('system-out');
        $system_out_element->appendChild($xml->createTextNode($test_case->system_out));
        $test_case_element->appendChild($system_out_element);
      }
      if (!empty($test_case->system_err)) {
        $system_err_element = $xml->createElement('system-err');
        $system_err_element->appendChild($xml->createTextNode($test_case->system_err));
        $test_case_element->appendChild($system_err_element);
      }
      $test_suite_element->appendChild($test_case_element);
    }
    $xml->appendChild($test_suite_element);
  }
  // Save to disk.
  file_put_contents($dir . '/testsuite-' . $test_id . '.xml', $xml->saveXML());
}

/**
 * Save test-results as human-viewable artifacts.
 *
 * This is called when the --artifacts option is set to a specific directory,
 * in which case the themed results tables, verbose messages, included css.
 * The results of this folder can then be saved as an artifact in your
 * continuous integration system for further viewing.
 *
 * @param $test_id
 *   An existing test id in the system
 * @param $dir
 *   A target directory
 * @name
 *   A non-sanitized name string
 *
 * @throws
 *   Exception
 */
function drush_test_artifact_results($test_id, $test_suites, $dir, $name) {
  $dir = is_string($dir) ? $dir : '.';
  // Build an XML document from our results.
  $form_state = array();
  module_load_include('inc', 'simpletest', 'simpletest.pages');
  $html = drupal_render_page(array('#type' => 'page', 'content' => simpletest_result_form(array(), $form_state, $test_id)));
  // Save to disk.
  file_put_contents($dir . '/_results-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) . '.html', $html);
  drush_test_artifact_copy_verbose_messages($dir);
  drush_test_artifact_correct_paths($dir);
}

/**
 * Copies html files from sites/../files/simpletest/verbose/*.html to directory
 *
 * @param $dir
 *   A directory to which to copy the files
 */
function drush_test_artifact_copy_verbose_messages($dir) {
  $files = file_scan_directory(file_default_scheme(). '://simpletest/verbose', '/.*\.html/');

  foreach ($files as $uri => $file) {
    // @TODO not sure how to get the relative path, see
    // http://stackoverflow.com/questions/23109916
    // "default" should not be hardcoded here.
    copy('sites/default/files/simpletest/verbose/' . $file->filename, $dir . '/' . $file->filename);
  }
}

/**
 * Where possible, correct files so that references to files resolve.
 *
 * Normally, the themed test report contains links to the verbose message
 * on the current URL, but because we're archiving the information, this
 * needs to be relative. The verbose messages themselves contain references
 * to CSS files on the current site. Again, because we are archiving these
 * as artifacts, we need to include the css files in this folder and reference
 * them correctly.
 */
function drush_test_artifact_correct_paths($dir) {
  $dir_resource = opendir($dir);
  if (!$dir_resource) {
    throw new Exception('Unknown directory');
  }
  while ($file = readdir($dir_resource)) {
    drush_test_artifact_correct_file($dir, $file);
  }
}

/**
 * Corrects a single file to save as an artifact.
 *
 * For test results, the correction will change links to files so they are
 * relative.
 * For verbose messages, the css will be imported, and the rereference
 * corrected.
 */
function drush_test_artifact_correct_file($dir, $file) {
  $corrector = DrushTestArtifactFileCorrector::Get($dir, $file);
  if ($corrector) {
    $corrector->Correct();
  }
}

class DrushTestArtifactFileCorrector {
  private $dir;
  private $file;
  function GetDir() {
    return $this->dir;
  }
  function GetFile() {
    return $this->file;
  }
  function __construct($dir, $file) {
    $this->dir = $dir;
    $this->file = $file;
  }
  static function Get($dir, $file) {
    if (substr($file, strlen($file) - strlen('.html')) == '.html' && strlen($file) > strlen('.html')) {
      if (substr($file, 0, 1) == '_') {
        return new DrushTestArtifactFileCorrector($dir, $file);
      }
      else {
        return new DrushTestArtifactFileCorrector($dir, $file);
      }
    }
  }
  function Filename() {
    return $this->GetDir() . '/' . $this->GetFile();
  }
  function Contents() {
    $return = file_get_contents($this->Filename());
    if ($return === FALSE) {
      throw new Exception('Could not read contents from file ' . $this->Filename);
    }
    return $return;
  }
  function SetContents($contents) {
    if (!$contents) {
      throw new Exception('Cannot remove all contents from file ' . get_calling_function());
    }
    if (file_put_contents($this->Filename(), $contents) === FALSE) {
      throw new Exception('Could not write contents to file ' . $this->Filename);
    }
  }
  function Correct() {
    $original_contents = $this->Contents();
    $corrected = $this->MakeRelativePaths();
    if ($corrected != $original_contents) {
      $this->SetContents($corrected);
    }
  }
  /**
   * Wrapper around preg_replace, throws Exception instead of returning NULL.
   */
  static function Replace($pattern, $replacement, $content) {
    $count = 0;
    $replaced = preg_replace($pattern, $replacement, $content, -1, $count);
    if ($replaced === NULL) {
      throw new Exception('preg_replace() could not be executed.');
    }
    return $replaced;
  }
  function GetExtensions() {
    return array(
      'css',
      'js',
      'png',
      'jpeg',
      'gif',
      'jpg',
      'html',
    );
  }
  function MakeRelativePaths() {
    $content = $this->Contents();
    global $base_url;
    $matches = array();
    // now replace the files locally
    $extensions = implode('|', $this->GetExtensions());
    self::CopyReferencedFiles();
    $pattern = '/"[:\/\.a-zA-Z0-9-_]*\/' . $this->FilePattern(). '"/';
    $content = self::Replace($pattern, '"\1\2"', $content);
    if (!$content) {
      throw new Exception('MakeRelativePaths() is not returning anything');
    }
    return $content;
  }

  function PathPattern() {
    return '([a-zA-Z0-9-_\/\.]*\/)';
  }

  function FilePattern() {
    $extensions = implode('|', $this->GetExtensions());
    return '([a-zA-Z0-9-_\.]*\.)(' . $extensions . ')[?]*[^a-zA-Z0-9-_\.]*';
  }

  /**
   * Copy all referenced files to the same level as the base file.
   *
   * The base file (this) might reference something like a/b/c/d.css or
   * x/y/z.png. We need to copy these as d.css and z.png at the same level
   * as this file.
   */
  function CopyReferencedFiles() {
    $content = $this->Contents();
    $pattern = '/\/' . $this->PathPattern() . $this->filePattern() . '/';
    global $base_url;
    $content = str_replace($base_url, '', $content);
    $matches = array();
    preg_match_all($pattern, $content, $matches);
    for ($i = 0; $i < count($matches[0]); $i++) {
      $file = $matches[1][$i] . '/' . $matches[2][$i] . $matches[3][$i];
      if (strpos($file, '/')) {
        if (file_exists($file)) {
          $destination = $this->getDir() . '/' . $matches[2][$i] . $matches[3][$i];
          copy($file, $destination);
          if (!file_exists($destination)) {
            throw new Exception('File ' . $destination . ' should exist');
          }
        }
      }
    }
  }
}

/**
* Returns the calling function through a backtrace
*/
function get_calling_function() {
  // a funciton x has called a function y which called this
  // see stackoverflow.com/questions/190421
  $caller = debug_backtrace();
  $caller = $caller[2];
  $r = $caller['function'] . '()';
  if (isset($caller['class'])) {
    $r .= ' in ' . $caller['class'];
  }
  if (isset($caller['object'])) {
    $r .= ' (' . get_class($caller['object']) . ')';
  }
  return $r;
}
