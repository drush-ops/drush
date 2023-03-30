<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\CacheCommands;
use Drush\Drupal\Commands\generate\GenerateCommands;
use Drush\Drupal\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

/**
 * @group commands
 */
class ModuleGeneratorTest extends UnishIntegrationTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests that generators provided by modules are registered.
     */
    public function testModuleGenerators(): void
    {
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '/../fixtures/modules'));
        $this->drush(PmCommands::INSTALL, ['woot']);
        $this->drush(CacheCommands::CLEAR, ['drush']);
        $this->drush(GenerateCommands::GENERATE, ['list']);
        $this->assertStringContainsString('woot:example', $this->getOutput());
        $this->assertStringContainsString('Generates a woot.', $this->getOutput());
    }

    public function tearDown(): void
    {

        $this->drush(PmCommands::UNINSTALL, ['woot']);
        parent::tearDown();
    }
}
