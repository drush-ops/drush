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

    // Test pm-download downloads a module and pm-list lists it.
    $this->drush('pm-download', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('no-core' => NULL, 'status' => 'disabled,not installed'));
    $out = $this->getOutput();
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    // Test pm-enable enables a module and shows the permissions it provides.
    $this->drush('pm-enable', array('devel'), $options_no_pipe);
    $output = $this->getOutput();
    $this->assertContains('access devel information', $output);

    // Test pm-list shows the module as enabled.
    $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    // Test pm-info shows some module info.
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
      $this->assertEquals($output->{$key}, $value);
    }

    // Test pm-projectinfo shows some project info.
    $this->drush('pm-projectinfo', array('devel'), $options);
    $output = $this->getOutputFromJSON('devel');
    $expected = array(
      'label' => 'Devel (devel)',
      'type' => 'module',
      'status' => '1',
    );
    foreach ($expected as $key => $value) {
      $this->assertEquals($output->{$key}, $value);
    }

    // Test the testing install profile theme is installed.
    $themeToCheck = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? 'classy' : (UNISH_DRUPAL_MAJOR_VERSION == 7 ? 'bartik' : 'garland');
    $this->assertTrue(in_array($themeToCheck, $list), 'Themes are in the pm-list');

    // Test cache was cleared after enabling a module.
    $table = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? 'router' : 'menu_router';
    $path = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? '/admin/config/development/devel' : (UNISH_DRUPAL_MAJOR_VERSION == 7 ? 'devel/settings' : 'admin/settings/devel');
    $this->drush('sql-query', array("SELECT path FROM $table WHERE path = '$path';"), array('root' => $this->webroot(), 'uri' => key($sites)));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array($path, $list), 'Cache was cleared after modules were enabled');

    // Test pm-list filtering.
    $this->drush('pm-list', array(), $options + array('package' => 'Core'));
    $list = $this->getOutputAsList();
    $this->assertFalse(in_array('devel', $list), 'Devel is not part of core package');

    // Test module disabling.
    if (UNISH_DRUPAL_MAJOR_VERSION <= 7) {
      $this->drush('pm-disable', array('devel'), $options);
      $this->drush('pm-list', array(), $options + array('status' => 'disabled'));
      $list = $this->getOutputAsList();
      $this->assertTrue(in_array('devel', $list));
    }

    // Test module uninstall.
    $this->drush('pm-uninstall', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'not installed', 'type' => 'module'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

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
