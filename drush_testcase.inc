<?php

abstract class Drush_TestCase extends PHPUnit_Framework_TestCase {
  
  function __construct() {
    $this->sandbox = sys_get_temp_dir() . '/drush-sandbox';
    $this->_output = false;
    // Assumes drush is on user's $PATH. Use absolute path as needed.
    $this->drush = 'drush';
  }
  
  /**
   * Assure that each case starts with an empty sandbox directory.
   */
  function setUp() {
    if (file_exists($this->sandbox)) {
      $this->file_delete_recursive($this->sandbox);
    }
    else {
      mkdir($this->sandbox);
    }
  }
  
  /**
   * Runs after each test case. Remove sandbox directory.
   */
  function tearDown() {
    if (file_exists($this->sandbox)) {
      $this->file_delete_recursive($this->sandbox);
    }
  }

  /**
   *    Actually runs the command. Does not trap the
   *    error stream output as this need PHP 4.3+.
   *    @param string $command    The actual command line
   *                              to run.
   *    @return integer           Exit code.
   *    @access public
   */
  function execute($command, $expected_return = 0) {
    $this->_output = false;
    exec($command, $this->_output, $return);
    $this->assertEquals($expected_return, $return, 'Unexpected return code: ' .  $command);
    return $return;
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