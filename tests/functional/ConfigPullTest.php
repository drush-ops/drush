<?php

declare(strict_types=1);

namespace Unish;

use PHPUnit\Framework\Attributes\Group;
use Drush\Commands\config\ConfigGetCommand;
use Drush\Commands\config\ConfigImportCommand;
use Drush\Commands\config\ConfigPullCommand;
use Drush\Commands\config\ConfigSetCommand;
use Symfony\Component\Filesystem\Path;

/**
 * Tests for config-pull command. Sets up two Drupal sites.
 */
#[Group('commands')]
#[Group('slow')]
#[Group('config')]
final class ConfigPullTest extends CommandUnishTestCase
{
    public function setup(): void
    {
        $this->setUpDrupal(2, true);
    }

  /*
   * Make sure a change propagates using config-pull+config-import.
   */
    public function testConfigPull(): void
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('rsync paths may not contain colons on Windows.');
        }

        $options = ['yes' => null, 'uri' => 'OMIT'];

        $aliases = $this->getAliases();
        $source = $aliases['stage'];
        $destination = $aliases['dev'];
        // Make UUID match.
        $this->drush(ConfigGetCommand::NAME, ['system.site', 'uuid'], $options, $source);
        [$name, $uuid] = explode(' ', $this->getOutput());
        $this->drush(ConfigSetCommand::NAME, ['system.site', 'uuid', $uuid], $options, $destination);

        $this->drush(ConfigSetCommand::NAME, ['system.site', 'name', 'testConfigPull'], $options, $source);
        $this->drush(ConfigPullCommand::NAME, [$source, $destination], $options);
        $this->drush(ConfigImportCommand::NAME, [], $options, $destination);
        $this->drush(ConfigGetCommand::NAME, ['system.site', 'name'], $options, $source);
        $this->assertSame("'system.site:name': testConfigPull", $this->getOutput(), 'Config was successfully pulled.');

        // Test that custom target dir works
        $target = Path::join($this->getSandbox(), self::class);
        $this->recursiveDelete($target);
        $this->mkdir($target);
        $this->drush(ConfigPullCommand::NAME, [$source, "$destination:$target"], $options);
        $this->assertFileExists(Path::join($target, 'system.site.yml'));
    }
}
