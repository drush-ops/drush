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
    $aliases = $this->getAliases();
    $source = $aliases['stage'];
    $destination = $aliases['dev'];
    // Make UUID match.
    $this->drush('config-get', ['system.site', 'uuid'], ['yes' => NULL], $source);
    list($name, $uuid) = explode(' ', $this->getOutput());
    $this->drush('config-set', ['system.site', 'uuid', $uuid], ['yes' => NULL], $destination);

    $this->drush('config-set', ['system.site', 'name', 'testConfigPull'], ['yes' => NULL], $source);
    $this->drush('config-pull', [$source, $destination], []);
    $this->drush('config-import', [], ['yes' => NULL], $destination);
    $this->drush('config-get', ['system.site', 'name'], [], $source);
    $this->assertEquals("'system.site:name': testConfigPull", $this->getOutput(), 'Config was successfully pulled.');
  }
}
