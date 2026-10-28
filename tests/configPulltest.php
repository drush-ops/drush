<?php

namespace Unish;


use PHPUnit\Framework\Attributes\Group;
/**
 * Tests for config-pull command. Sets up two Drupal sites.
 */
#[Group('commands')]
#[Group('slow')]
#[Group('config')]
class configPulltest extends CommandUnishTestCase {

  function set_up() {
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped('Config only available on D8+.');
    }

    $this->setUpDrupal(2, TRUE);
  }

  /*
   * Make sure a change propogates using config-pull+config-import.
   */
  function testConfigPull() {
    list($source, $destination) = array_keys($this->getSites());
    $source = "@$source";
    $destination = "@$destination";
    $this->drush('config-set', array('system.site', 'name', 'testConfigPull'), array('yes' => NULL), $source);
    $this->drush('config-pull', array($source, $destination), array());
    $this->drush('config-import', array(), array(), $destination);
    $this->drush('config-get', array('system.site', 'name'), array(), $source);
    $this->assertEquals("'system.site:name': testConfigPull", $this->getOutput(), 'Config was successfully pulled.');
  }
}
