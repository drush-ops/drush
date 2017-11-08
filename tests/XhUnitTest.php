<?php

namespace Unish;
use Drush\Commands\core\XhprofCommands;

/**
 * Unit tests for xhCommands
 *
 * @group base
 */
class XhUnitCase extends UnishTestCase {

  /**
   * Test various combinations of XHProf flag options.
   *
   * @dataProvider xhOptionProvider
   */
    public function testFlags($name, $options, $expected)
    {
        $this->assertEquals($expected, XhprofCommands::xhprofFlags($options), $name);
    }

  /**
   * Provides drush XHProf options and the results we expect from xh_flags().
   */
    public function xhOptionProvider()
    {

        if (!defined('XHPROF_FLAGS_NO_BUILTINS')) {
            define('XHPROF_FLAGS_NO_BUILTINS', 1);
            define('XHPROF_FLAGS_CPU', 2);
            define('XHPROF_FLAGS_MEMORY', 3);
        }

        return [
        [
        'name' => 'Default flag options explicitly provided',
        'options' => [
          'profile-builtins' => true,
          'profile-cpu' => false,
          'profile-memory' => false,
        ],
        'expected' => 0,
        ],
        [
        'name' => 'Disable profiling of built-ins',
        'options' => [
          'profile-builtins' => false,
          'profile-cpu' => false,
          'profile-memory' => false,
        ],
        'expected' => XHPROF_FLAGS_NO_BUILTINS,
        ],
        [
        'name' => 'Enable profiling of CPU',
        'options' => [
          'profile-builtins' => true,
          'profile-cpu' => true,
          'profile-memory' => false,
        ],
        'expected' => XHPROF_FLAGS_CPU,
        ],
        [
        'name' => 'Enable profiling of CPU, without builtins',
        'options' => [
          'profile-builtins' => false,
          'profile-cpu' => true,
          'profile-memory' => false,
        ],
        'expected' => XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_CPU,
        ],
        [
        'name' => 'Enable profiling of Memory',
        'options' => [
          'profile-builtins' => true,
          'profile-cpu' => false,
          'profile-memory' => true,
        ],
        'expected' => XHPROF_FLAGS_MEMORY,
        ],
        [
        'name' => 'Enable profiling of Memory, without builtins',
        'options' => [
          'profile-builtins' => false,
          'profile-cpu' => false,
          'profile-memory' => true,
        ],
        'expected' => XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_MEMORY,
        ],
        [
        'name' => 'Enable profiling of CPU & Memory',
        'options' => [
          'profile-builtins' => true,
          'profile-cpu' => true,
          'profile-memory' => true,
        ],
        'expected' => XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY,
        ],
        ];
    }

}
