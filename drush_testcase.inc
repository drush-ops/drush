<?php

abstract class Drush_TestCase extends PHPUnit_Framework_TestCase {
  
  function __construct() {
    $this->_output = false;
  }
  
  /**
   * Assure that each case starts with an empty sandbox directory.
   */
  function setUp() {
    if (file_exists(UNISH_SANDBOX)) {
      $this->file_delete_recursive(UNISH_SANDBOX);
    }
    else {
      mkdir(UNISH_SANDBOX);
    }
  }
  
  /**
   * Runs after each test case. Remove sandbox directory.
   */
  function tearDown() {
    if (file_exists(UNISH_SANDBOX)) {
      $this->file_delete_recursive(UNISH_SANDBOX);
    }
  }

  /**
   * Actually runs the command. Does not trap the error stream output as this
   * need PHP 4.3+.
   *
   * @param string $command
   *   The actual command line to run.
   * @return integer
   *   Exit code.
   */
  function execute($command, $expected_return = 0) {
    $this->_output = false;
    // todo check verbose level from phpunit.
    if (1) {
      print "\nExecuting: $command \n";
    }
    exec($command, $this->_output, $return);
    $this->assertEquals($expected_return, $return, 'Unexpected return code: ' .  $command);
    return $return;
  }
  
  /**
   * Invoke drush in via execute().
   *
   * @param command
    *    A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
    * @param args
    *   Command arguments.
    * @param $options
    *    Optional. An associative array containing options.
    * @return integer
    *   An exit code.
    */
  function drush($command, $args = array(), $options = array()) {
    $cmd = UNISH_DRUSH . ' ' . $command;
    foreach ($args as $arg) {
      $cmd .= ' ' . escapeshellarg($arg);
    }
    $pairs = array();
    foreach ($options as $key => $value) {
      if (is_null($value)) {
        $pairs[] = "--$key";
      }
      else {
        $pairs[] = "--$key=$value";
      }
    }
    $cmd .= ' ' . implode(' ', $pairs);
    return $this->execute($cmd);
  }

  /**
   *    Accessor for the last output.
   *    @return string        Output as text.
   *    @access public
   */
  function getOutput() {
    return implode("\n", $this->_output);
  }

  /**
   *    Accessor for the last output.
   *    @return array         Output as array of lines.
   *    @access public
   */
  function getOutputAsList() {
    return $this->_output;
  }
  
  /**
   * Delete all files and directories under a path.
   * 
   * @param string $path
   *   An absolute filesystem path.
   * 
   * @return boolean   
   *   True is success.
   */
  function file_delete_recursive($path) {
    if (is_dir($path)) {
      $dir = dir($path);
      while (($entry = $dir->read()) !== FALSE) {
        if ($entry == '.' || $entry == '..') {
          continue;
        }
        $entry_path = $path . '/' . $entry;
        $this->file_delete_recursive($entry_path);
      }
      $dir->close();
  
      return rmdir($path);
    }
    return unlink($path);
  }
}

abstract class Drush_DrupalTestCase extends Drush_TestCase {
  function setUp() {
    parent::setUp();
    $this->setUpDrupal();
  }
  
  function setUpDrupal($env = 'dev') {
    $root = UNISH_SANDBOX . '/web';

    // Download Drupal if not already present.
    if (!file_exists($root)) {
      $options = array(
        'destination' => UNISH_SANDBOX,
        'drupal-project-rename' => 'web',
        'yes' => NULL,
      );
      $this->drush('pm-download', array('drupal-7.x'), $options);
    }

    // Install Drupal as a multi-site.
    $options = array(
      'root' => $root,
      'db-url' => UNISH_DB_URL . '/unish_' . $env,
      'sites-subdir' => $env,
      'yes' => NULL,
    );
    $this->drush('site-install', array(), $options);
    // Give us our write perms back.
    $ret = chmod("$root/sites/$env", 0777);

    // Stash details about this site.
    $this->sites[$env] = array(
      'root' => $root,
      'db_url' => UNISH_DB_URL . '/unish_' . $env,
    );
  }
}