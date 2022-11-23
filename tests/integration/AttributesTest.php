<?php

namespace Unish;

use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Symfony\Component\Console\Tester\CommandCompletionTester;

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

    /**
     * @requires PHP >= 8.0
     */
    public function testCompletion()
    {
        if (!class_exists('\Symfony\Component\Console\Completion\Output\FishCompletionOutput')) {
            $this->markTestSkipped('Symfony Console 6.2+ needed for rest this test.');
        }

        $this->commandFileInstance = new \Custom\Library\Drush\Commands\ExampleAttributesDrushCommands();
        $this->commandFactory = new AnnotatedCommandFactory();
        $commandInfo = $this->commandFactory->createCommandInfo($this->commandFileInstance, 'testArithmatic');
        $command = $this->commandFactory->createCommand($commandInfo, $this->commandFileInstance);
        $this->assertIsCallable($command->getCompletionCallback());

        $tester = new CommandCompletionTester($command);
        // Complete the input without any existing input (the empty string represents
        // the position of the cursor)
        $suggestions = $tester->complete(['']);
        $this->assertSame(['1', '2', '3', '4', '5'], $suggestions);

        $suggestions = $tester->complete(['1', '2', '--color']);
        $this->assertSame(['red', 'blue', 'green'], $suggestions);

        // CommandCompletionTester from Symfony doesnt test dynamic values as
        // that is our feature. Symfony uses closures for this but we can't use closures
        // in Attributes.
        // $suggestions = $tester->complete(['1', '12']);
        // $this->assertSame(['12', '121', '122'], $suggestions);
    }
}
