<?php

namespace Unish;

/**
 * Make makefile tests.
 * @group make
 * @group slow
 */
class lockMakefileCase extends CommandUnishTestCase {
  /**
   * Path to test make files.
   */
  protected $makefile_path;

  /**
   * Initialize $makefile_path.
   */
  function __construct() {
    $this->makefile_path =  dirname(__FILE__) . DIRECTORY_SEPARATOR . 'makefiles';
  }

  /**
   * Run a given makefile test.
   *
   * @param $test
   *   The test makefile to run, as defined by $this->getMakefile();
   */
  private function runLockfileTest($test) {
    $default_options = array(
      'result-file' => UNISH_SANDBOX . '/test.lock.yml',
    );
    $config = $this->getLockfile($test);
    $options = array_merge($config['options'], $default_options);
    $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
    $lockfile = $this->makefile_path . DIRECTORY_SEPARATOR . 'lockfiles' . DIRECTORY_SEPARATOR . $config['lockfile'];
    $this->drush('make-lock', array($makefile), $options);
    $expected = trim(file_get_contents($lockfile));
    $actual = trim(file_get_contents($options['result-file']));

    $this->assertEquals($expected, $actual);
  }

  function getLockfile($key) {
    static $tests;
    $tests = $this->listLockfileTests();
    return $tests[$key];
  }

  function listLockfileTests() {
    $tests = array(
      'default' => array(
        'name'     => 'lock',
        'makefile' => 'lock-default.make.yml',
        'lockfile' => 'default.lock.yml',
        'options'  => array(),
      ),
      'git' => array(
        'name'     => 'git',
        'makefile' => 'lock-git.make.yml',
        'lockfile' => 'git.lock.yml',
        'options'  => array(),
      ),
    );
    return $tests;
  }

  /************************************************************************
   *                                                                      *
   *  List of lock tests (in alphabetical order, for easier navigation.)  *
   *                                                                      *
   ************************************************************************/

  /**
   * Test locking basic version data.
   */
  function testDefaultLock() {
    $this->runLockfileTest('default');
  }

  /**
   * Test locking git version data.
   */
  function testGitLock() {
    $this->runLockfileTest('git');
  }

}
