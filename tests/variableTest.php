<?php

/**
 * @file
 *   Tests for enable, disable, uninstall, pm-list commands.
 *
 * @group commands
 */
class VariableCase extends Drush_CommandTestCase {

  function testVariable() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'pipe' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    $this->drush('variable-set', array('test_integer', '3.14159'), $options);
    $this->drush('variable-get', array('test_integer'), $options);
    $var_export = $this->getOutput();
    eval($var_export);
    $this->assertEquals("3.14159", $variables['test_integer'], 'Integer variable was successfully set and get.');

    $this->drush('variable-set', array('date_default_timezone', 'US/Mountain'), $options);
    $this->drush('variable-get', array('date_default_timezone'), $options); // Wildcard get.
    $var_export = $this->getOutput();
    eval($var_export);
    $this->assertEquals('US/Mountain', $variables['date_default_timezone'], 'Variable was successfully set and get.');

    $this->drush('variable-set', array('site_name', 'control'), $options + array('exact' => NULL));
    $this->drush('variable-set', array('site_na', 'unish'), $options + array('always-set' => NULL));
    $this->drush('variable-get', array('site_na'), $options + array('exact' => NULL));
    $var_export = $this->getOutput();
    eval($var_export);
    $this->assertEquals('unish', $variables['site_na'], '--always-set option works as expected.');

    $this->drush('variable-set', array('site_n', 'exactish'), $options + array('exact' => NULL));
    $this->drush('variable-get', array('site_n'), $options + array('exact' => NULL));
    $var_export = $this->getOutput();
    eval($var_export);
    $this->assertEquals('exactish', $variables['site_n'], '--exact option works as expected.');

    $this->drush('variable-delete', array('site_name'), $options);
    $output = $this->getOutput();
    $this->assertEmpty($output, 'Variable was successfully deleted.');
  }
}
