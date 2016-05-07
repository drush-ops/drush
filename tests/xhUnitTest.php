<?php

namespace Unish;

/**
 * Unit tests for xh.drush.inc.
 *
 * @group base
 */
class xhUnitCase extends UnitUnishTestCase {

  /**
   * Test various combinations of XHProf flag options.
   *
   * @dataProvider xhOptionProvider
   */
  public function testFlags($name, $options, $expected) {
    drush_preflight();
    foreach ($options as $option_name => $option_value) {
      drush_set_option($option_name, $option_value);
    }
    $this->assertEquals($expected, xh_flags(), $name);
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
        'name' => 'No flag options provided (default)',
        'options' => array(),
        'expected' => 0,
      ),
      array(
        'name' => 'Default flag options explicitly provided',
        'options' => array(
          'xh-profile-builtins' => TRUE,
          'xh-profile-cpu' => FALSE,
          'xh-profile-memory' => FALSE,
        ),
        'expected' => 0,
      ),
      array(
        'name' => 'Disable profiling of built-ins',
        'options' => array(
          'xh-profile-builtins' => FALSE,
          'xh-profile-cpu' => FALSE,
          'xh-profile-memory' => FALSE,
        ),
        'expected' => XHPROF_FLAGS_NO_BUILTINS,
      ),
      array(
        'name' => 'Enable profiling of CPU',
        'options' => array(
          'xh-profile-builtins' => TRUE,
          'xh-profile-cpu' => TRUE,
          'xh-profile-memory' => FALSE,
        ),
        'expected' => XHPROF_FLAGS_CPU,
      ),
      array(
        'name' => 'Enable profiling of CPU, without builtins',
        'options' => array(
          'xh-profile-builtins' => FALSE,
          'xh-profile-cpu' => TRUE,
          'xh-profile-memory' => FALSE,
        ),
        'expected' => XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_CPU,
      ),
      array(
        'name' => 'Enable profiling of Memory',
        'options' => array(
          'xh-profile-builtins' => TRUE,
          'xh-profile-cpu' => FALSE,
          'xh-profile-memory' => TRUE,
        ),
        'expected' => XHPROF_FLAGS_MEMORY,
      ),
      array(
        'name' => 'Enable profiling of Memory, without builtins',
        'options' => array(
          'xh-profile-builtins' => FALSE,
          'xh-profile-cpu' => FALSE,
          'xh-profile-memory' => TRUE,
        ),
        'expected' => XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_MEMORY,
      ),
      array(
        'name' => 'Enable profiling of CPU & Memory',
        'options' => array(
          'xh-profile-builtins' => TRUE,
          'xh-profile-cpu' => TRUE,
          'xh-profile-memory' => TRUE,
        ),
        'expected' => XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY,
      ),
    );
  }

}
