<?php

namespace Unish;

/**
 * @group commands
 */
class WatchdogTest extends UnishIntegrationTestCase
{
    public function testWatchdog()
    {
        $this->drush('pm-install', ['dblog']);
        $this->drush('watchdog-delete', ['all'], ['yes' => true]);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('All watchdog messages have been deleted', $output);

        $eval1 = "\\Drupal::logger('drush')->notice('Unish rocks.');";
        $this->drush('php-eval', [$eval1]);
        $this->drush('watchdog-show', [], ['count' => 50]);
        $output = $this->getOutput();
        $this->assertStringContainsString('Unish rocks.', $output);

        // Add a new entry with a long message with the letter 'd' and verify that watchdog-show does
        // not print it completely in the listing unless --full is given.
        // As the output is formatted so lines may be splitted, assertStringContainsString does not work
        // in this scenario. Therefore, we will count the number of times a character is present.
        $message_chars = 300;
        $char = '*';
        $message = str_repeat($char, $message_chars);
        $eval2 = "\\Drupal::logger('drush')->notice('$message');";
        $this->drush('php-eval', [$eval2]);
        $this->drush('watchdog-show');
        $output = $this->getOutput();
        $this->assertStringContainsString('Unish rocks', $output);
        $this->assertGreaterThan(substr_count($output, $char), $message_chars);
        $this->drush('watchdog-show', [], ['extended' => null]);
        $output = $this->getOutput();
        $this->assertGreaterThanOrEqual($message_chars, substr_count($output, $char));

        // Test deleting a watchdog message by filtering on text.
        $this->drush('watchdog-delete', ['\*\*\*'], ['yes' => true]);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('1 watchdog messages have been deleted.', $output);

        // Add a warning message and an alert message, for testing the severity parameters.
        $eval3 = "\\Drupal::logger('drush')->warning('Rocking Unish.');";
        $this->drush('php-eval', [$eval3]);
        $eval4 = "\\Drupal::logger('drush')->alert('Beware! Rocks of Unish ahead');";
        $this->drush('php-eval', [$eval4]);

        // Test the 'severity' parameter, to show only messages with a severity of Notice.
        $this->drush('watchdog-show', [], ['severity' => 'Notice']);
        $output = $this->getOutput();
        $this->assertStringContainsString('Unish rocks.', $output);
        $this->assertStringContainsString('Notice', $output);
        $this->assertStringNotContainsString('Warning', $output);
        $this->assertStringNotContainsString('Alert', $output);
        $this->assertStringNotContainsString(str_repeat($char, 20), $output);

        // Test the 'severity-min' parameter, to show all messages with a severity of Warning
        // and higher. This should not include the notice message.
        $this->drush('watchdog-show', [], ['severity-min' => 'Warning']);
        $output = $this->getOutput();
        $this->assertStringNotContainsString('Notice', $output);
        $this->assertStringContainsString('Warning', $output);
        $this->assertStringContainsString('Alert', $output);

        // Tests message deletion
        $this->drush('watchdog-delete', ['all'], ['yes' => true]);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('All watchdog messages have been deleted', $output);
        $this->drush('watchdog-show');
        $output = $this->getOutput();
        $this->assertEquals('', $output);
    }
}
