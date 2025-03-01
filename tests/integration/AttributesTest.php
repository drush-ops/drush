<?php

declare(strict_types=1);

namespace Unish;

use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Custom\Library\Drush\Commands\ExampleAttributesCommands;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Filesystem\Path;

/**
 * Tests commands defined using PHP 8+ attributes.
 *
 * @group commands
 */
class AttributesTest extends UnishIntegrationTestCase
{
    private ExampleAttributesCommands $commandFileInstance;
    private AnnotatedCommandFactory $commandFactory;

    public function testAttributes()
    {
        $options = [];

        // Hook declaration test
        $this->drush('my:echo', ['foo', 'bar'], $options);
        $this->assertStringNotContainsString("HOOKED", $this->getOutput());
        $this->drush('test:arithmatic', ['9'], $options);
        $this->assertStringContainsString("HOOKED", $this->getOutput());

        // Table Attributes
        $this->drush('birds', [], $options + ['format' => 'json', 'filter' => 'Cardinal']);
        $data = $this->getOutputFromJSON('cardinal');
        $this->assertEquals(['color' => 'red'], $data);

        // Validators and Bootstrap test
        $sandbox = Path::join($this->getSandbox());
        $this->drush('validatestuff', ['access df', $sandbox, 'authenticated'], $options, self::EXIT_ERROR);
        $this->assertErrorOutputContains('Permission(s) not found: access df');
        $this->drush('validatestuff', ['access content', Path::join($sandbox, 'dfdf'), 'authenticated'], $options, self::EXIT_ERROR);
        $this->assertErrorOutputContains('File(s) not found: ' . Path::join($sandbox, 'dfdf'));
        $this->drush('validatestuff', ['access content', $sandbox, 'authenticatedddndndn'], $options, self::EXIT_ERROR);
        $this->assertErrorOutputContains('Unable to load the user_role: authenticatedddndndn');
        // Finally, expect success.
        $this->drush('validatestuff', ['access content', $sandbox, 'authenticated'], $options, self::EXIT_SUCCESS);
    }

    public function testCompletion()
    {
        if (!class_exists('\Symfony\Component\Console\Completion\Output\FishCompletionOutput')) {
            $this->markTestSkipped('Symfony Console 6.2+ needed for rest this test.');
        }

        $this->commandFileInstance = new ExampleAttributesCommands();
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
