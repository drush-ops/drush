<?php

namespace Unish;

if (class_exists('Unish\CommandUnishTestCase')) {

  /**
   * PHPUnit Tests for devel. This uses Drush's own test framework, based on PHPUnit.
   * To run the tests, use run-tests-drush.sh from the devel directory.
   */
  class develCase extends CommandUnishTestCase {

    public function testFnCommands() {
      // Specify '8' just in case user has not set UNISH_DRUPAL_MAJOR_VERSION env variable.
      $sites = $this->setUpDrupal(1, TRUE, '8');

      // Symlink this module into the Site Under test so it can be enabled.
      $target = dirname(__DIR__);
      \symlink($target, $this->webroot() . '/modules/devel');
      $options = array(
        'root' => $this->webroot(),
        'uri' => key($sites),
      );
      $this->drush('pm-enable', array('devel'), $options + array('skip' => NULL, 'yes' => NULL));

      $this->drush('fn-view', array('drush_main'), $options);
      $output = $this->getOutput();
      $this->assertContains('@return', $output, 'Output contain @return Doxygen.');
      $this->assertContains('function drush_main() {', $output, 'Output contains function drush_main() declaration');

  //    $this->drush('fn-hook', array('cron'), $options);
  //    $output = $this->getOutputAsList();
  //    $expected = array('dblog', 'file', 'field', 'system', 'update');
  //    $this->assertSame($expected, $output);
    }
  }

}
