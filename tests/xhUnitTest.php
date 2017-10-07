<?php

namespace Unish;
use Drush\Commands\core\XhprofCommands;

/**
 * Unit tests for xh.drush.inc.
 *
 * @group base
 */
class xhUnitCase extends UnishTestCase {

  /**
   * Test various combinations of XHProf flag options.
   *
   * @dataProvider xhOptionProvider
   */
  public function testFlags($name, $options, $expected) {
    $this->assertEquals($expected, XhprofCommands::xhprofFlags($options), $name);
  }

  /**
   * Provides drush XHProf options and the results we expect from xh_flags().
   */
  public function xhOptionProvider() {

    if (!defined('XHPROF_FLAGS_NO_BUILTINS')) {
      define('XHPROF_FLAGS_NO_BUILTINS', 1);
      define('XHPROF_FLAGS_CPU', 2);
      define('XHPROF_FLAGS_MEMORY', 3);
    }

    return array(
      array(
        'name' => 'Default flag options explicitly provided',
        'options' => array(
          'profile-builtins' => TRUE,
          'profile-cpu' => FALSE,
          'profile-memory' => FALSE,
        ),
        'expected' => 0,
      ),
      array(
        'name' => 'Disable profiling of built-ins',
        'options' => array(
          'profile-builtins' => FALSE,
          'profile-cpu' => FALSE,
          'profile-memory' => FALSE,
        ),
        'expected' => XHPROF_FLAGS_NO_BUILTINS,
      ),
      array(
        'name' => 'Enable profiling of CPU',
        'options' => array(
          'profile-builtins' => TRUE,
          'profile-cpu' => TRUE,
          'profile-memory' => FALSE,
        ),
        'expected' => XHPROF_FLAGS_CPU,
      ),
      array(
        'name' => 'Enable profiling of CPU, without builtins',
        'options' => array(
          'profile-builtins' => FALSE,
          'profile-cpu' => TRUE,
          'profile-memory' => FALSE,
        ),
        'expected' => XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_CPU,
      ),
      array(
        'name' => 'Enable profiling of Memory',
        'options' => array(
          'profile-builtins' => TRUE,
          'profile-cpu' => FALSE,
          'profile-memory' => TRUE,
        ),
        'expected' => XHPROF_FLAGS_MEMORY,
      ),
      array(
        'name' => 'Enable profiling of Memory, without builtins',
        'options' => array(
          'profile-builtins' => FALSE,
          'profile-cpu' => FALSE,
          'profile-memory' => TRUE,
        ),
        'expected' => XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_MEMORY,
      ),
      array(
        'name' => 'Enable profiling of CPU & Memory',
        'options' => array(
          'profile-builtins' => TRUE,
          'profile-cpu' => TRUE,
          'profile-memory' => TRUE,
        ),
        'expected' => XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY,
      ),
    );
  }

}
