<?php

declare(strict_types=1);

namespace Drush\TestTraits;

/**
 * DrushTestTrait provides a `drush()` method that may be
 * used to write functional tests for Drush extensions.
 *
 * More information is available at https://github.com/drush-ops/drush/blob/13.x/docs/contribute/unish.md#drush-test-traits.
 */
trait DrushTestTrait
{
    use CliTestTrait;

    public function getPathToDrush(): string
    {
        return dirname(__DIR__, 2) . '/drush';
    }

    /**
     * Invoke drush in via execute().
     *
     * @param $command
     *   A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
     * @param $args
     *   Command arguments.
     * @param $options
     *   An associative array containing options.
     * @param $site_specification
     *   A site alias or site specification. Include the '@' at start of a site alias.
     * @param $cd
     *   A directory to change into before executing.
     * @param $expected_return
     *   The expected exit code, e.g. 0 or 1 or some other expected value.
     * @param $suffix
     *   Any code to append to the command. For example, redirection like 2>&1.
     * @param $env
     *   Environment variables to pass along to the subprocess.
     */
    public function drush($command, array $args = [], array $options = [], ?string $site_specification = null, ?string $cd = null, int $expected_return = 0, ?string $suffix = null, array $env = []): void
    {
        // Set UA for SimpleTests which typically extend BrowserTestCase (i.e. contrib modules).
        if (isset($this->databasePrefix) && function_exists('drupal_generate_test_ua') && !isset($env['HTTP_USER_AGENT'])) {
            $env['HTTP_USER_AGENT'] = drupal_generate_test_ua($this->databasePrefix);
        }

        $global_option_list = ['simulate', 'root', 'uri', 'include', 'config', 'alias-path', 'ssh-options'];
        $cmd[] = self::getPathToDrush();

        // Insert global options.
        foreach ($options as $key => $value) {
            if (in_array($key, $global_option_list)) {
                unset($options[$key]);
                $cmd[] = $this->convertKeyValueToFlag($key, $value);
            }
        }

        $cmd[] = "--no-interaction";

        // Insert site specification and drush command.
        if (!empty($site_specification)) {
            $cmd[] = self::escapeshellarg($site_specification);
        }
        $cmd[] = $command;

        // Insert drush command arguments.
        foreach ($args as $arg) {
            $cmd[] = self::escapeshellarg((string) $arg);
        }
        // insert drush command options
        foreach ($options as $key => $value) {
            $cmd[] = $this->convertKeyValueToFlag($key, $value);
        }

        $cmd[] = (string) $suffix;
        $exec = array_filter($cmd, 'strlen'); // Removes empty strings.

        $cmd = implode(' ', $exec);
        $this->execute($cmd, $expected_return, $cd, $env);
    }

    /**
     * Given an option key / value pair, convert to a "--key=value" string.
     */
    protected function convertKeyValueToFlag(string $key, mixed $value): string
    {
        if (!isset($value) || $value === true) {
            return "--$key";
        }
        return "--$key=" . self::escapeshellarg((string)$value);
    }

    /**
     * Return the major version of Drush
     *
     * @return int e.g. 11 or 12 or ...
     */
    public function drushMajorVersion(): int
    {
        static $major;

        if (!isset($major)) {
            $this->drush('version', [], ['field' => 'drush-version']);
            $version = trim($this->getOutput());
            list($major) = explode('.', $version);
        }
        return (int)$major;
    }
}
