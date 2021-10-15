<?php

namespace Unish;

use Webmozart\PathUtil\Path;

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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '../functional/resources/modules/d8'));
        $this->drush('pm:enable', ['woot']);
        $this->drush('cc', ['drush']);
        $this->drush('generate', ['list']);
        $this->assertStringContainsString('woot:example', $this->getOutput());
        $this->assertStringContainsString('Generates a woot.', $this->getOutput());
    }
}
