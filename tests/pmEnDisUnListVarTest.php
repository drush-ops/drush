<?php

/*
 * @file
 *   Tests for enable, disable, uninstall, pm-list, and variable-* commands.
 */
class EnDisUnListVarCase extends Drush_TestCase {

  public function testEnDisUnListVar() {
    $this->setUpDrupal('dev', TRUE);
    $options = array(
      'yes' => NULL,
      'pipe' => NULL,
      'root' => $this->sites['dev']['root'],
      'uri' => 'dev',
    );
    $this->drush('pm-download', array('devel-7.x-1.0'), $options);
    $this->drush('pm-list', array(), $options + array('no-core' => NULL, 'status' => 'not installed'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    $this->drush('pm-enable', array('menu', 'devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'enabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));
    $this->assertTrue(in_array('bartik', $list), 'Themes are in the pm-list');

    // A brief detour to test variable-*.
    $this->drush('variable-set', array('devel_query_display', TRUE), $options);
    $this->drush('variable-get', array('devel'), $options); // Wildcard get.
    $var_export = $this->getOutput();
    eval($var_export);
    $this->assertEquals('1', $variables['devel_query_display'], 'Variable was successfully set and get.');
    $this->drush('variable-set', array('site_name', 'unish'), $options + array('always-set' => NULL));
    $this->drush('variable-get', array('site_name'), $options);
    $var_export = $this->getOutput();
    eval($var_export);
    $this->assertEquals('unish', $variables['site_name'], '--always-set option works as expected.');
    $this->drush('variable-delete', array('site_name'), $options);
    $output = $this->getOutput();
    $this->assertEmpty($output, 'Variable was successfully deleted.');

    $this->drush('pm-list', array(), $options + array('package' => 'Core'));
    $list = $this->getOutputAsList();
    $this->assertFalse(in_array('devel', $list), 'Devel is not part of core package');

    $this->drush('pm-disable', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'disabled'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    $this->drush('pm-uninstall', array('devel'), $options);
    $this->drush('pm-list', array(), $options + array('status' => 'not installed', 'type' => 'module'));
    $list = $this->getOutputAsList();
    $this->assertTrue(in_array('devel', $list));

    // We expect an exit code of 1 so just call execute() directly.
    $exec = sprintf('%s variable-get %s --pipe --root=%s --uri=%s', UNISH_DRUSH, 'devel_query_display', $options['root'], $options['uri']);
    $this->execute($exec, self::EXIT_ERROR);
    $output = $this->getOutput();
    $this->assertEmpty($output, 'Devel variable was uninstalled.');
  }
}