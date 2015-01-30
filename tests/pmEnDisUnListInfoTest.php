<?php

/**
 * @file
 *   Tests for enable, disable, uninstall, pm-list commands.
 */

namespace Unish;

/**
 *  @group slow
 *  @group pm
 */
class EnDisUnListInfoCase extends CommandUnishTestCase {

  public function testEnDisUnList() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options_no_pipe = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'strict' => 0, // Don't validate options
    );
    $options = $options_no_pipe + array(
      'pipe' => NULL,
    );
    $this->drush('pm-download', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('no-core' => NULL, 'status' => 'disabled,not installed'));
    $out = $this->getOutput();
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    $this->drush('pm-enable', array('devel'), $options_no_pipe);
    $output = $this->getOutput();
    $this->assertContains('access devel information', $output);
    $this->drush('pm-info', array('devel'), $options);
    $output = $this->getOutputFromJSON('devel');
    $expected = array(
      'extension' => 'devel',
      'project' => 'devel',
      'type' => 'module',
      'title' => 'Devel',
      'status' => 'enabled',
    );
    foreach ($expected as $key => $value) {
      $this->assertEquals($expected[$key], $value);
    }

    $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));
    // In D7, the testing profile uses 'bartik', whereas in D8, 'classy' is used.
    $themeToCheck = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? 'classy' : (UNISH_DRUPAL_MAJOR_VERSION == 7 ? 'bartik' : 'garland');
    $this->assertTrue(in_array($themeToCheck, $list), 'Themes are in the pm-list');

    if (UNISH_DRUPAL_MAJOR_VERSION <= 7) {
      $path = UNISH_DRUPAL_MAJOR_VERSION == 7 ? 'devel/settings' : 'admin/settings/devel';
      $this->drush('sql-query', array("SELECT path FROM menu_router WHERE path = '$path';"), array('root' => $this->webroot(), 'uri' => key($sites)));
      $list = $this->getOutputAsList();
      $this->assertTrue(in_array($path, $list), 'Cache was cleared after modules were enabled');
    }

    $this->drush('pm-list', array(), $options + array('package' => 'Core'));
    $list = $this->getOutputAsList();
    $this->assertFalse(in_array('devel', $list), 'Devel is not part of core package');

    if (UNISH_DRUPAL_MAJOR_VERSION <= 7) {
      $this->drush('pm-disable', array('devel'), $options);
      $this->drush('pm-list', array(), $options + array('status' => 'disabled'));
      $list = $this->getOutputAsList();
      $this->assertTrue(in_array('devel', $list));
    }

    $this->drush('pm-uninstall', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'not installed', 'type' => 'module'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    // Test pm-enable is able to download dependencies.
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $this->markTestSkipped("pathauto does not have a release for Drupal 8 yet.");
    }

    $this->drush('pm-download', array('pathauto'), $options);
    $this->drush('pm-enable', array('pathauto'), $options + array('resolve-dependencies' => TRUE));
    $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('token', $list));

    // Test that pm-enable downloads missing projects and dependencies.
    $this->drush('pm-enable', array('views'), $options + array('resolve-dependencies' => TRUE));
    $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
    $list = $this->getOutputAsList();
    // @todo fails in D6
    $this->assertTrue(in_array('ctools', $list));
  }
}
