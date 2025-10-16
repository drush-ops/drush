<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\config\ConfigPullCommands;
use Drush\Commands\config\ConfigCommands;
use Drush\Commands\config\ConfigImportCommands;
use Symfony\Component\Filesystem\Path;

/**
 * Tests for config-pull command. Sets up two Drupal sites.
 * @group commands
 * @group slow
 * @group config
 */
class ConfigPullTest extends CommandUnishTestCase
{
    public function setup(): void
    {
        $this->setUpDrupal(2, true);
    }

  /*
   * Make sure a change propagates using config-pull+config-import.
   */
    public function testConfigPull()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('rsync paths may not contain colons on Windows.');
        }

        $options = ['yes' => null, 'uri' => 'OMIT'];

        $aliases = $this->getAliases();
        $source = $aliases['stage'];
        $destination = $aliases['dev'];
        // Make UUID match.
        $this->drush(ConfigCommands::GET, ['system.site', 'uuid'], $options, $source);
        list($name, $uuid) = explode(' ', $this->getOutput());
        $this->drush(ConfigCommands::SET, ['system.site', 'uuid', $uuid], $options, $destination);

        $this->drush(ConfigCommands::SET, ['system.site', 'name', 'testConfigPull'], $options, $source);
        $this->drush(ConfigPullCommands::PULL, [$source, $destination], $options);
        $this->drush(ConfigImportCommands::IMPORT, [], $options, $destination);
        $this->drush(ConfigCommands::GET, ['system.site', 'name'], $options, $source);
        $this->assertEquals("'system.site:name': testConfigPull", $this->getOutput(), 'Config was successfully pulled.');

        // Test that custom target dir works
        $target = Path::join($this->getSandbox(), __CLASS__);
        $this->recursiveDelete($target);
        $this->mkdir($target);
        $this->drush(ConfigPullCommands::PULL, [$source, "$destination:$target"], $options);
        $this->assertFileExists(Path::join($target, 'system.site.yml'));
    }
}
