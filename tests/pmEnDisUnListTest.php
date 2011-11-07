<?php

/*
 * @file
 *   Tests for enable, disable, uninstall, pm-list commands.
 */
class EnDisUnListCase extends Drush_CommandTestCase {

  public function testEnDisUnList() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'pipe' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'invoke' => NULL, // Don't validate options
    );
    $this->drush('pm-download', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('no-core' => NULL, 'status' => 'not installed'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    $this->drush('pm-enable', array('menu', 'devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));
    $this->assertTrue(in_array('bartik', $list), 'Themes are in the pm-list');

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
  }
}
