<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\PhpCommands;
use Drush\Commands\core\WatchdogCommands;
use Drush\Commands\pm\PmCommands;

/**
 * @group commands
 */
class WatchdogTest extends UnishIntegrationTestCase
{
    public function testWatchdogShow()
    {
        $this->drush(PmCommands::INSTALL, ['dblog']);
        $this->drush(WatchdogCommands::DELETE, ['all'], ['yes' => true]);

        $eval1 = "\\Drupal::logger('drush')->notice('Unish rocks.');";
        $this->drush(PhpCommands::EVAL, [$eval1]);
        $this->drush(WatchdogCommands::SHOW, [], ['count' => 50]);
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
        $this->drush(PhpCommands::EVAL, [$eval2]);
        $this->drush(WatchdogCommands::SHOW);
        $output = $this->getOutput();
        $this->assertStringContainsString('Unish rocks', $output);
        $this->assertGreaterThan(substr_count($output, $char), $message_chars);
        $this->drush(WatchdogCommands::SHOW, [], ['extended' => null]);
        $output = $this->getOutput();
        $this->assertGreaterThanOrEqual($message_chars, substr_count($output, $char));

        // Add a warning message and an alert message, for testing the severity parameters.
        $eval3 = "\\Drupal::logger('drush')->warning('Rocking Unish.');";
        $this->drush(PhpCommands::EVAL, [$eval3]);
        $eval4 = "\\Drupal::logger('drush')->alert('Beware! Rocks of Unish ahead');";
        $this->drush(PhpCommands::EVAL, [$eval4]);

        // Test the 'severity' parameter, to show only messages with a severity of Notice.
        $this->drush(WatchdogCommands::SHOW, [], ['severity' => 'Notice']);
        $output = $this->getOutput();
        $this->assertStringContainsString('Unish rocks.', $output);
        $this->assertStringContainsString('Notice', $output);
        $this->assertStringNotContainsString('Warning', $output);
        $this->assertStringNotContainsString('Alert', $output);

        // Test the 'severity-min' parameter, to show all messages with a severity of Warning
        // and higher. This should not include the notice message.
        $this->drush(WatchdogCommands::SHOW, [], ['severity-min' => 'Warning']);
        $output = $this->getOutput();
        $this->assertStringNotContainsString('Notice', $output);
        $this->assertStringContainsString('Warning', $output);
        $this->assertStringContainsString('Alert', $output);
    }

    public function testWatchdogDelete()
    {
        // Test deleting all messages.
        $this->drush(WatchdogCommands::DELETE, ['all'], ['yes' => true]);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('All watchdog messages have been deleted', $output);
        $this->drush(WatchdogCommands::SHOW);
        $output = $this->getOutput();
        $this->assertEquals('', $output);

        // Create messages.
        $eval1 = "\\Drupal::logger('other')->info('Scrub');";
        $this->drush(PhpCommands::EVAL, [$eval1]);
        $eval2 = "\\Drupal::logger('drush')->notice('Delete');";
        $this->drush(PhpCommands::EVAL, [$eval2]);
        $eval3 = "\\Drupal::logger('drush')->warning('Eliminate');";
        $this->drush(PhpCommands::EVAL, [$eval3]);
        $eval4 = "\\Drupal::logger('drush')->error('Obliterate');";
        $this->drush(PhpCommands::EVAL, [$eval4]);
        $eval5 = "\\Drupal::logger('drush')->critical('*** Exterminate!');";
        $this->drush(PhpCommands::EVAL, [$eval5]);
        $this->showAll();

        // Show that all the messages have been stored.
        $this->drush(WatchdogCommands::SHOW);
        $output = $this->getOutput();
        $this->assertStringContainsString('Scrub', $output);
        $this->assertStringContainsString('Delete', $output);
        $this->assertStringContainsString('Eliminate', $output);
        $this->assertStringContainsString('Obliterate', $output);
        $this->assertStringContainsString('Exterminate', $output);

        // Test deleting a single message by id.
        // The ids are different depending on the database used. With mysql the
        // the new messages start from 1. postgres and sqlite continue on from
        // the deleted messages. Therefore skip this.
        // $this->drush('watchdog-delete', [2], ['yes' => true]);
        // $output = $this->getErrorOutput();
        // $this->assertStringContainsString('Watchdog message #2 has been deleted.', $output);
        // $this->assertStringNotContainsString('Delete', $output);
        // $this->showAll();

        // Test deleting messages by severity.
        $this->drush(WatchdogCommands::DELETE, [], ['severity' => 'Warning', 'yes' => true]);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('1 watchdog messages have been deleted', $output);
        $this->assertStringNotContainsString('Eliminate', $output);
        $this->showAll();

        // Test deleting messages by type.
        $this->drush(WatchdogCommands::DELETE, [], ['type' => 'other', 'yes' => true]);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('1 watchdog messages have been deleted', $output);
        $this->assertStringNotContainsString('Scrub', $output);
        $this->showAll();

        // Test deleting a watchdog message by filtering on text.
        // @todo Investigate why using '\*\*\*' to match '***' does not work on
        // sqlite CircleCI tests when it passes on mysql and postgres tests.
        $this->drush(WatchdogCommands::DELETE, ['\*\*\*'], ['yes' => true]);
        $output = $this->getErrorOutput();
        $this->showAll();
        // So also delete by matching ordinary words.
        $this->drush(WatchdogCommands::DELETE, ['Exterminate'], ['yes' => true]);
        $output .= $this->getErrorOutput();
        $this->showAll();
        $this->assertStringContainsString('1 watchdog messages have been deleted.', $output);
        $this->assertStringNotContainsString('Exterminate', $output);

        // Finally delete all messages.
        $this->drush(WatchdogCommands::DELETE, ['all'], ['yes' => true]);
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('All watchdog messages have been deleted', $output);
        $this->drush(WatchdogCommands::SHOW);
        $output = $this->getOutput();
        $this->assertEquals('', $output);
        $this->showAll();
    }

    private function showAll()
    {
        // Helper (debug) function to show all watchdog messages.
        static $count;
        $count += 1;
        $this->drush(WatchdogCommands::SHOW);
        $output = $this->getOutput();
        print "\n>>>>> {$count}\n" . $output . "\n";
    }
}
