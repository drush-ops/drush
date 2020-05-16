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
    $moduleToTest = 'devel';
    $expectedTitle = 'Devel';
    if (UNISH_DRUPAL_MAJOR_VERSION >= 9) {
      $moduleToTest = 'token';
      $expectedTitle = 'Token';
    }
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

    // Test pm-download downloads a module and pm-list lists it.
    $this->drush('pm-download', array($moduleToTest), $options);
    $this->drush('pm-list', array(), $options + array('no-core' => NULL, 'status' => 'disabled,not installed'));
    $out = $this->getOutput();
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array($moduleToTest, $list));

    // Test pm-enable enables a module and shows the permissions it provides.
    $this->drush('pm-enable', array($moduleToTest), $options_no_pipe);
    $output = $this->getOutput();
    if ($moduleToTest == 'devel') {
      $this->assertContains('access devel information', $output);
    }

    // Test pm-list shows the module as enabled.
    $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array($moduleToTest, $list));

    // Test pm-info shows some module info.
    $this->drush('pm-info', array($moduleToTest), $options);
    $output = $this->getOutputFromJSON($moduleToTest);
    $expected = array(
      'extension' => $moduleToTest,
      'project' => $moduleToTest,
      'type' => 'module',
      'title' => $expectedTitle,
      'status' => 'enabled',
    );
    foreach ($expected as $key => $value) {
      $this->assertEquals($output->{$key}, $value);
    }

    // Test pm-projectinfo shows some project info.
    $this->drush('pm-projectinfo', array($moduleToTest), $options);
    $output = $this->getOutputFromJSON($moduleToTest);
    $expected = array(
      'label' => "$expectedTitle ($moduleToTest)",
      'type' => 'module',
      'status' => '1',
    );
    foreach ($expected as $key => $value) {
      $this->assertEquals($output->{$key}, $value);
    }

    // Test the testing install profile theme is installed.
    $themeToCheck = 'garland';
    if (UNISH_DRUPAL_MAJOR_VERSION >= 7) {
      $themeToCheck = 'bartik';
    }
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $themeToCheck = 'stark';
      // UNISH_DRUPAL_MINOR_VERSION is something like ".8.0-alpha1".
      if (UNISH_DRUPAL_MINOR_VERSION[1] <= 8) {
        $themeToCheck = 'classy';
        $this->markTestSkipped('Project "panels", used in this test, no longer works with earlier versions of Drupal 8.');
      }
    }
    if (UNISH_DRUPAL_MAJOR_VERSION >= 9) {
      $themeToCheck = 'stark';
    }
    $this->assertContains($themeToCheck, $list, 'Themes are in the pm-list');

    // Test cache was cleared after enabling a module.
    $table = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? 'router' : 'menu_router';
    $path = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? '/admin/config/development/devel' : (UNISH_DRUPAL_MAJOR_VERSION == 7 ? 'devel/settings' : 'admin/settings/devel');
    $this->drush('sql-query', array("SELECT path FROM $table WHERE path = '$path';"), array('root' => $this->webroot(), 'uri' => key($sites)));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array($path, $list), 'Cache was cleared after modules were enabled');

    // Test pm-list filtering.
    $this->drush('pm-list', array(), $options + array('package' => 'Core'));
    $list = $this->getOutputAsList();
    $this->assertFalse(in_array($moduleToTest, $list), 'Module to test is not part of core package');

    // Test module disabling.
    if (UNISH_DRUPAL_MAJOR_VERSION <= 7) {
      $this->drush('pm-disable', array($moduleToTest), $options);
      $this->drush('pm-list', array(), $options + array('status' => 'disabled'));
      $list = $this->getOutputAsList();
      $this->assertTrue(in_array($moduleToTest, $list));
    }

    // Test module uninstall.
    $this->drush('pm-uninstall', array($moduleToTest), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'not installed', 'type' => 'module'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array($moduleToTest, $list));

    // Test pm-enable is able to download dependencies.
    // @todo pathauto has no usable D8 release yet.
    // Also, Drupal 6 has no stable releases any longer, so resolve-dependencies are inconvenient to test.
    if (UNISH_DRUPAL_MAJOR_VERSION ==7) {
      $this->drush('pm-download', array('pathauto'), $options);
      $this->drush('pm-enable', array('pathauto'), $options + array('resolve-dependencies' => TRUE));
      $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
      $list = $this->getOutputAsList();
      $this->assertTrue(in_array('token', $list));
    }

    if (UNISH_DRUPAL_MAJOR_VERSION !=6) {
      if (substr(UNISH_DRUPAL_MINOR_VERSION, 0, 2) == '.4') {
        $this->markTestSkipped("panels module broken with Drupal 8.4.x");
      }
      // Test that pm-enable downloads missing projects and dependencies.
      $this->drush('pm-enable', array('panels'), $options + array('resolve-dependencies' => TRUE));
      $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
      $list = $this->getOutputAsList();
      $this->assertTrue(in_array('ctools', $list));
    }

    // Test that pm-enable downloads missing projects
    // and dependencies with project namespace (date:date_popup).
    if (UNISH_DRUPAL_MAJOR_VERSION == 7) {
      $this->drush('pm-enable', array('date_datepicker_inline'), $options + array('resolve-dependencies' => TRUE));
      $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
      $list = $this->getOutputAsList();
      $this->assertTrue(in_array('date_popup', $list));
    }

  }
}
