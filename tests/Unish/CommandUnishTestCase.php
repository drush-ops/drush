<?php

namespace Unish;

abstract class CommandUnishTestCase extends UnishTestCase {

  // Unix exit codes.
  const EXIT_SUCCESS  = 0;
  const EXIT_ERROR = 1;

  /*
   * Array of code coverage data collected during a single test.
   */
  var $coverage_data = array();

  /**
   * Actually runs the command. Does not trap the error stream output as this
   * need PHP 4.3+.
   *
   * @param string $command
   *   The actual command line to run.
   * @param integer $expected_return
   *   The return code to expect
   * @param array $env
   *   Extra environment variables
   * @return integer
   *   Exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
   */
  function execute($command, $expected_return = self::EXIT_SUCCESS, $env = array()) {
    $this->_output = FALSE;
    $return = 1;
    $this->log("Executing: $command", 'notice');

    // Apply the environment variables we need for our test
    // to the head of the command
    $prefix = '';
    foreach ($env as $env_name => $env_value) {
      $prefix .= $env_name . '=' . self::escapeshellarg($env_value) . ' ';
    }
    exec($prefix . $command, $this->_output, $return);

    $this->assertEquals($expected_return, $return, 'Unexpected exit code: ' .  $command);
    return $return;
  }

  /**
   * Invoke drush in via execute().
   *
   * @param command
    *   A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
    * @param args
    *   Command arguments.
    * @param $options
    *   An associative array containing options.
    * @param $site_specification
    *   A site alias or site specification. Include the '@' at start of a site alias.
    * @param $cd
    *   A directory to change into before executing.
    * @param $expected_return
    *   The expected exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
    * @param $suffix
    *   Any code to append to the command. For example, redirection like 2>&1.
    * @return integer
    *   An exit code.
    */
  function drush($command, array $args = array(), array $options = array(), $site_specification = NULL, $cd = NULL, $expected_return = self::EXIT_SUCCESS, $suffix = NULL, $env = array()) {
    $global_option_list = array('simulate', 'root', 'uri', 'include', 'config', 'alias-path', 'ssh-options', 'backend');
    $hide_stderr = FALSE;
    // insert "cd ... ; drush"
    $cmd[] = $cd ? sprintf('cd %s &&', self::escapeshellarg($cd)) : NULL;
    $cmd[] = UNISH_DRUSH;

    // insert global options
    foreach ($options as $key => $value) {
      if (in_array($key, $global_option_list)) {
        unset($options[$key]);
        if ($key == 'backend') {
          $hide_stderr = TRUE;
          $value = NULL;
        }
        if (!isset($value)) {
          $cmd[] = "--$key";
        }
        else {
          $cmd[] = "--$key=" . self::escapeshellarg($value);
        }
      }
    }

    if ($level = $this->log_level()) {
      $cmd[] = '--' . $level;
    }
    $cmd[] = "--nocolor";

    // Insert code coverage argument before command, in order for it to be
    // parsed as a global option. This matters for commands like ssh and rsync
    // where options after the command are passed along to external commands.
    $result = $this->getTestResultObject();
    if ($result->getCollectCodeCoverageInformation()) {
      $coverage_file = tempnam(UNISH_TMP, 'drush_coverage');
      if ($coverage_file) {
        $cmd[] = "--drush-coverage=" . $coverage_file;
      }
    }

    // insert site specification and drush command
    $cmd[] = empty($site_specification) ? NULL : self::escapeshellarg($site_specification);
    $cmd[] = $command;

    // insert drush command arguments
    foreach ($args as $arg) {
      $cmd[] = self::escapeshellarg($arg);
    }
    // insert drush command options
    foreach ($options as $key => $value) {
      if (!isset($value)) {
        $cmd[] = "--$key";
      }
      else {
        $cmd[] = "--$key=" . self::escapeshellarg($value);
      }
    }

    $cmd[] = $suffix;
    if ($hide_stderr) {
      $cmd[] = '2>/dev/null';
    }
    $exec = array_filter($cmd, 'strlen'); // Remove NULLs
    // set sendmail_path to 'true' to disable any outgoing emails
    // that tests might cause Drupal to send.
    $php_options = (array_key_exists('PHP_OPTIONS', $env)) ? $env['PHP_OPTIONS'] . " " : "";
    $env['PHP_OPTIONS'] = "${php_options}-d sendmail_path='true'";
    $return = $this->execute(implode(' ', $exec), $expected_return, $env);

    // Save code coverage information.
    if (!empty($coverage_file)) {
      $data = unserialize(file_get_contents($coverage_file));
      unlink($coverage_file);
      // Save for appending after the test finishes.
      $this->coverage_data[] = $data;
    }

    return $return;
  }

  /**
   * Override the run method, so we can add in our code coverage data after the
   * test has run.
   *
   * We have to collect all coverage data, merge them and append them as one, to
   * avoid having phpUnit duplicating the test function as many times as drush
   * has been invoked.
   *
   * Runs the test case and collects the results in a TestResult object.
   * If no TestResult object is passed a new one will be created.
   *
   * @param  PHPUnit_Framework_TestResult $result
   * @return PHPUnit_Framework_TestResult
   * @throws PHPUnit_Framework_Exception
   */
  public function run(\PHPUnit_Framework_TestResult $result = NULL) {
    $result = parent::run($result);
    $data = array();
    foreach ($this->coverage_data as $merge_data) {
      foreach ($merge_data as $file => $lines) {
        if (!isset($data[$file])) {
          $data[$file] = $lines;
        }
        else {
          foreach ($lines as $num => $executed) {
            if (!isset($data[$file][$num])) {
              $data[$file][$num] = $executed;
            }
            else {
              $data[$file][$num] = ($executed == 1 ? $executed : $data[$file][$num]);
            }
          }
        }
      }
    }

    // Reset coverage data.
    $this->coverage_data = array();
    if (!empty($data)) {
      $result->getCodeCoverage()->append($data, $this);
    }
    return $result;
  }

  /**
   * A slightly less functional copy of drush_backend_parse_output().
   */
  function parse_backend_output($string) {
    $regex = sprintf(UNISH_BACKEND_OUTPUT_DELIMITER, '(.*)');
    preg_match("/$regex/s", $string, $match);
    if ($match[1]) {
      // we have our JSON encoded string
      $output = $match[1];
      // remove the match we just made and any non printing characters
      $string = trim(str_replace(sprintf(UNISH_BACKEND_OUTPUT_DELIMITER, $match[1]), '', $string));
    }

    if ($output) {
      $data = json_decode($output, TRUE);
      if (is_array($data)) {
        return $data;
      }
    }
    return $string;
  }

  function drush_major_version() {
    static $major;

    if (!isset($major)) {
      $this->drush('version', array('drush_version'), array('pipe' => NULL));
      $version = trim($this->getOutput());
      list($major) = explode('.', $version);
    }
    return (int)$major;
  }
}
