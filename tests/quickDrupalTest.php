<?php

namespace Unish;

/**
 * core-quick-drupal tests.
 *
 * @group quick-drupal
 * @group slow
 */
class quickDrupalCase extends CommandUnishTestCase {
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
   * Run a given quick-drupal test.
   *
   * @param $test
   *   The test makefile to run, as defined by $this->getQuickDrupalTestParameters();
   */
  private function runQuickDrupalTest($test) {
    $config = $this->getQuickDrupalTestParameters($test);
    $default_options = array(
      'yes' => NULL,
      'no-server' => NULL,
    );
    $options = array_merge($config['options'], $default_options);
    if (array_key_exists('makefile', $config)) {
      $makefile = $this->makefile_path . DIRECTORY_SEPARATOR . $config['makefile'];
      $options['makefile'] = $makefile;
    }
    $return = !empty($config['fail']) ? self::EXIT_ERROR : self::EXIT_SUCCESS;
    $target = UNISH_SANDBOX . '/qd-' . $test;
    $options['root'] = $target;
    $this->drush('core-quick-drupal', $config['args'], $options, NULL, NULL, $return);

    // Use pm-list to determine if all of the correct modules were enabled
    if (empty($config['fail'])) {
      $this->drush('pm-list', array(), array('root' => $target, 'status' => 'enabled', 'no-core' => NULL, 'pipe' => NULL));
      $output = $this->getOutput();
      $this->assertEquals($config['expected-modules'], $output, 'quick-drupal included the correct set of modules');
    }
  }

  function testQuickDrupal() {
    $this->runQuickDrupalTest('devel');
  }

  function getQuickDrupalTestParameters($key) {
    $tests = array(
      'devel' => array(
        'name'     => 'Test quick-drupal with a makefile that downloads devel',
        'makefile' => 'qd-devel.make',
        'expected-modules' => 'devel',
        'args' => array(),
        'options'  => array(
          'skip' => NULL, // for speed up enable of devel module.
          'browser' => 0,
          'profile' => UNISH_DRUPAL_MAJOR_VERSION == 6 ? 'standard' : 'testing',
        ),
      ),
    );
    return $tests[$key];
  }
}
