<?php
namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * Tests for config-pull command. Sets up two Drupal sites.
 * @group commands
 * @group slow
 * @group config
 */
class ConfigPullCase extends CommandUnishTestCase
{

    public function setUp()
    {
        $this->setUpDrupal(2, true);
    }

  /*
   * Make sure a change propagates using config-pull+config-import.
   */
    public function testConfigPull()
    {
        $aliases = $this->getAliases();
        $source = $aliases['stage'];
        $destination = $aliases['dev'];
        // Make UUID match.
        $this->drush('config-get', ['system.site', 'uuid'], ['yes' => null], $source);
        list($name, $uuid) = explode(' ', $this->getOutput());
        $this->drush('config-set', ['system.site', 'uuid', $uuid], ['yes' => null], $destination);

        $this->drush('config-set', ['system.site', 'name', 'testConfigPull'], ['yes' => null], $source);
        $this->drush('config-pull', [$source, $destination]);
        $this->drush('config-import', [], ['yes' => null], $destination);
        $this->drush('config-get', ['system.site', 'name'], [], $source);
        $this->assertEquals("'system.site:name': testConfigPull", $this->getOutput(), 'Config was successfully pulled.');

        // Test that custom target dir works
        $target = Path::join($this->getSandbox(), __CLASS__);
        $this->mkdir($target);
        $this->drush('config-pull', [$source, "$destination:$target"]);
        $this->assertFileExists(Path::join($target, 'system.site.yml'));
    }
}
