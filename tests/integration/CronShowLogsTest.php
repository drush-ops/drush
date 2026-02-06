<?php

declare(strict_types=1);

namespace Unish;

/**
 * Tests for core:cron command with --show-drupal-logs option.
 *
 * @group commands
 */
class CronShowLogsTest extends UnishIntegrationTestCase
{
    /**
     * Test basic cron execution without logs option.
     */
    public function testCronWithoutLogsOption(): void
    {
        $this->drush('core:cron');
        $output = $this->getErrorOutput();

        // Should not contain log output header
        $this->assertStringNotContainsString('Drupal logs generated during cron execution:', $output);
    }

    /**
     * Test cron with --show-drupal-logs when dblog is enabled.
     */
    public function testCronShowLogsWithDblog(): void
    {
        // Enable dblog module
        $this->drush('pm:enable', ['dblog']);

        // Run cron with --show-drupal-logs
        $this->drush('core:cron', [], ['show-drupal-logs' => null]);
        $output = $this->getErrorOutput();

        // Should contain log output header or "No Drupal logs" message
        $this->assertTrue(
            str_contains($output, 'Drupal logs generated during cron execution:') ||
            str_contains($output, 'No Drupal logs generated during cron execution.')
        );
    }

    /**
     * Test cron with --show-drupal-logs and actual log generation.
     */
    public function testCronShowLogsGeneratesOutput(): void
    {
        // Enable dblog
        $this->drush('pm:enable', ['dblog']);

        // Clear existing logs
        $this->drush('watchdog:delete', ['all'], ['yes' => true]);

        // Create a log entry
        $eval = "\\Drupal::logger('test')->info('Test cron log message');";
        $this->drush('php:eval', [$eval]);

        // Run cron with --show-drupal-logs
        $this->drush('core:cron', [], ['show-drupal-logs' => null]);
        $output = $this->getErrorOutput();

        // Should show logs section
        $this->assertStringContainsString('Drupal logs generated during cron execution:', $output);
    }

    /**
     * Test cron with --show-drupal-logs and --log-severity filter.
     */
    public function testCronShowLogsWithSeverityFilter(): void
    {
        // Enable dblog
        $this->drush('pm:enable', ['dblog']);

        // Clear logs
        $this->drush('watchdog:delete', ['all'], ['yes' => true]);

        // Create logs with different severities
        $eval1 = "\\Drupal::logger('test')->info('Test info message');";
        $eval2 = "\\Drupal::logger('test')->warning('Test warning message');";
        $this->drush('php:eval', [$eval1]);
        $this->drush('php:eval', [$eval2]);

        // Run cron with severity filter (only warnings and above, level 4)
        $this->drush('core:cron', [], ['show-drupal-logs' => null, 'log-severity' => 4]);
        $output = $this->getErrorOutput();

        // Should contain WARNING but not INFO
        if (str_contains($output, 'Drupal logs generated during cron execution:')) {
            $this->assertStringContainsString('WARNING', $output);
        }
    }

    /**
     * Test cron with --show-drupal-logs and --log-type filter.
     */
    public function testCronShowLogsWithTypeFilter(): void
    {
        // Enable dblog
        $this->drush('pm:enable', ['dblog']);

        // Clear logs
        $this->drush('watchdog:delete', ['all'], ['yes' => true]);

        // Create logs with different types
        $eval1 = "\\Drupal::logger('test_type')->info('Test message');";
        $eval2 = "\\Drupal::logger('other_type')->info('Other message');";
        $this->drush('php:eval', [$eval1]);
        $this->drush('php:eval', [$eval2]);

        // Run cron with type filter
        $this->drush('core:cron', [], ['show-drupal-logs' => null, 'log-type' => 'test_type']);
        $output = $this->getErrorOutput();

        // Should only show logs of filtered type
        if (str_contains($output, 'Drupal logs generated during cron execution:')) {
            $this->assertStringContainsString('[test_type]', $output);
        }
    }
}
