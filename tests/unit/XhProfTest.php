<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\XhprofCommands;
use Drush\Config\DrushConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for xhCommands
 *
 * @group base
 */
class XhProfTest extends TestCase
{
  /**
   * Test various combinations of XHProf flag options.
   *
   * @dataProvider xhOptionProvider
   */
    public function testFlags($name, $options, $expected)
    {
        $config = new DrushConfig();
        foreach ($options as $key => $value) {
            $config->set('xh.' . $key, $value);
        }
        $this->assertEquals($expected, XhprofCommands::xhprofFlags($config), $name);
    }

  /**
   * Provides drush XHProf options and the results we expect from xh_flags().
   */
    public static function xhOptionProvider()
    {

        if (!defined('XHPROF_FLAGS_NO_BUILTINS')) {
            define('XHPROF_FLAGS_NO_BUILTINS', 1);
            define('XHPROF_FLAGS_CPU', 2);
            define('XHPROF_FLAGS_MEMORY', 4);
        }
        if (!defined('TIDEWAYS_XHPROF_FLAGS_NO_BUILTINS')) {
            define('TIDEWAYS_XHPROF_FLAGS_NO_BUILTINS', 8);
            define('TIDEWAYS_XHPROF_FLAGS_CPU', 1);
            define('TIDEWAYS_XHPROF_FLAGS_MEMORY', 6);
        }
        if (extension_loaded('tideways_xhprof')) {
            $flags = [
                'no-builtins' => TIDEWAYS_XHPROF_FLAGS_NO_BUILTINS,
                'cpu' => TIDEWAYS_XHPROF_FLAGS_CPU,
                'memory' => TIDEWAYS_XHPROF_FLAGS_MEMORY,
            ];
        } else {
            $flags = [
                'no-builtins' => XHPROF_FLAGS_NO_BUILTINS,
                'cpu' => XHPROF_FLAGS_CPU,
                'memory' => XHPROF_FLAGS_MEMORY,
            ];
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
        'expected' => $flags['no-builtins'],
        ],
        [
        'name' => 'Enable profiling of CPU',
        'options' => [
          'profile-builtins' => true,
          'profile-cpu' => true,
          'profile-memory' => false,
        ],
        'expected' => $flags['cpu'],
        ],
        [
        'name' => 'Enable profiling of CPU, without builtins',
        'options' => [
          'profile-builtins' => false,
          'profile-cpu' => true,
          'profile-memory' => false,
        ],
        'expected' => $flags['no-builtins'] | $flags['cpu'],
        ],
        [
        'name' => 'Enable profiling of Memory',
        'options' => [
          'profile-builtins' => true,
          'profile-cpu' => false,
          'profile-memory' => true,
        ],
        'expected' => $flags['memory'],
        ],
        [
        'name' => 'Enable profiling of Memory, without builtins',
        'options' => [
          'profile-builtins' => false,
          'profile-cpu' => false,
          'profile-memory' => true,
        ],
        'expected' => $flags['no-builtins'] | $flags['memory'],
        ],
        [
        'name' => 'Enable profiling of CPU & Memory',
        'options' => [
          'profile-builtins' => true,
          'profile-cpu' => true,
          'profile-memory' => true,
        ],
        'expected' => $flags['cpu'] | $flags['memory'],
        ],
        ];
    }
}
