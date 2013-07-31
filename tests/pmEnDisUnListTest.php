<?php

/**
 * @file
 *   Tests for enable, disable, uninstall, pm-list commands.
 */

/**
 *  @group slow
 *  @group pm
 */
class EnDisUnListCase extends Drush_CommandTestCase {

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
    $this->drush('pm-list', array(), $options + array('no-core' => NULL, 'status' => 'not installed'));
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
    // In D7, the testing profile uses 'bartik', whereas in D8, 'stark' is used.
    $themeToCheck = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? 'stark' : (UNISH_DRUPAL_MAJOR_VERSION == 7 ? 'bartik' : 'garland');
    $this->assertTrue(in_array($themeToCheck, $list), 'Themes are in the pm-list');

    $this->drush('sql-query', array("SELECT path FROM menu_router WHERE path = 'devel/settings';"), array('root' => $this->webroot(), 'uri' => key($sites)));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel/settings', $list), 'Cache was cleared after modules were enabled');

    $this->drush('pm-list', array(), $options + array('package' => 'Core'));
    $list = $this->getOutputAsList();
    $this->assertFalse(in_array('devel', $list), 'Devel is not part of core package');

    // For testing uninstall later.
    $this->drush('variable-set', array('devel_query_display', 1), $options);

    $this->drush('pm-disable', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'disabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    $this->drush('pm-uninstall', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'not installed', 'type' => 'module'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    $this->drush('variable-get', array('devel_query_display'), $options, NULL, NULL, self::EXIT_ERROR);
    $output = $this->getOutput();
    $this->assertEmpty($output, 'Devel variable was uninstalled.');

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
    $this->assertTrue(in_array('ctools', $list));
  }
}
