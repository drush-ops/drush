<?php

namespace Unish;

use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Drush\Commands\ExampleAttributesCommands;

/**
 * Tests commands defined using PHP 8+ attributes.
 *
 * @group commands
 */
class AttributesTest extends UnishIntegrationTestCase
{
    /**
     * @requires PHP >= 8.0
     */
    public function testAttributes()
    {
        $options = [];

        // Hook declaration test
        $this->drush('my:echo', ['foo', 'bar'], $options);
        $this->assertStringNotContainsString("HOOKED", $this->getOutput());
        $this->drush('test:arithmatic', ['9'], $options);
        $this->assertOutputEquals("HOOKED\n11");

        // Table Attributes
        $this->drush('birds', [], $options + ['format' => 'json', 'filter' => 'Cardinal']);
        $data = $this->getOutputFromJSON('cardinal');
        $this->assertEquals(['color' => 'red'], $data);

        // Validators and Bootstrap test
        $this->drush('validatestuff', ['access df', '/tmp', 'authenticated'], $options, self::EXIT_ERROR);
        $this->assertErrorOutputContains('Permission(s) not found: access df');
        $this->drush('validatestuff', ['access content', '/tmp/dfdf', 'authenticated'], $options, self::EXIT_ERROR);
        $this->assertErrorOutputContains('File(s) not found: /tmp/dfdf');
        $this->drush('validatestuff', ['access content', '/tmp', 'authenticatedddndndn'], $options, self::EXIT_ERROR);
        $this->assertErrorOutputContains('Unable to load the user_role: authenticatedddndndn');
        // Finally, expect success.
        $this->drush('validatestuff', ['access content', '/tmp', 'authenticated'], $options, self::EXIT_SUCCESS);
    }
}
