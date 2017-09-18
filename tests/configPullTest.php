<?php

namespace Unish;

/**
 * Tests for config-pull command. Sets up two Drupal sites.
 * @group commands
 * @group slow
 * @group config
 */
class ConfigPullCase extends CommandUnishTestCase {

  function setUp() {
    $this->setUpDrupal(2, TRUE);
  }

  /*
   * Make sure a change propagates using config-pull+config-import.
   */
  function testConfigPull() {
    list($source, $destination) = array_keys($this->getSites());
    $source = "@$source";
    $destination = "@$destination";
    // Make UUID match.
    $this->drush('config-get', array('system.site', 'uuid'), array('yes' => NULL), $source);
    list($name, $uuid) = explode(' ', $this->getOutput());
    $this->drush('config-set', array('system.site', 'uuid', $uuid), array('yes' => NULL), $destination);

    $this->drush('config-set', array('system.site', 'name', 'testConfigPull'), array('yes' => NULL), $source);
    $this->drush('config-pull', array($source, $destination), array());
    $this->drush('config-import', array(), array('yes' => NULL), $destination);
    $this->drush('config-get', array('system.site', 'name'), array(), $source);
    $this->assertEquals("'system.site:name': testConfigPull", $this->getOutput(), 'Config was successfully pulled.');
  }
}
