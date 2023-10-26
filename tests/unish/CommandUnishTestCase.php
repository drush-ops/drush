<?php

namespace Unish;

use Drush\TestTraits\CliTestTrait;
use Symfony\Component\Process\Process;

abstract class CommandUnishTestCase extends UnishTestCase
{
    use CliTestTrait;

    public const WOOT_SERVICES_PATH = 'modules/unish/woot/woot.services.yml';
    public const WOOT_INFO_PATH = 'modules/unish/woot/woot.info.yml';

    /**
     * Code coverage data collected during a single test.
     */
    protected array $coverage_data = [];

    /**
     * Accessor for the last output, non-trimmed.
     */
    public function getOutputRaw(): string
    {
        return $this->process ? $this->process->getOutput() : '';
    }

    /**
     * Accessor for the last stderr output, non-trimmed.
     */
    public function getErrorOutputRaw(): string
    {
        return $this->process ? $this->process->getErrorOutput() : '';
    }

    /**
     * Invoke drush command via startExecute(), and return the resulting process.
     *
     * Use this method when you need to interact with the Drush command under
     * test while it is still running. Currently used to test watchdog:tail, runserver.
     *
     * @param command
     *   A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
     * @param args
     *   Command arguments.
     * @param $options
     *   An associative array containing options.
     * @param $site_specification
     *   A site alias or site specification. Include the '@' at start of a site alias.
     * @param $cd
     *   A directory to change into before executing.
     * @param $suffix
     *   Any code to append to the command. For example, redirection like 2>&1.
     * @param array $env
     *   Environment variables to pass along to the subprocess.
     * @return Process
     *   A Symfony Process object.
     */
    public function drushBackground(string $command, array $args = [], array $options = [], $site_specification = null, $cd = null, $suffix = null, $env = [])
    {
        list($cmd, ) = $this->prepareDrushCommand($command, $args, $options, $site_specification, $suffix);
        return $this->startExecute(explode(' ', $cmd), $cd, $env);
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
      *   The expected exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
      * @param $suffix
      *   Any code to append to the command. For example, redirection like 2>&1.
      * @param array $env
      *   Environment variables to pass along to the subprocess.
      */
    public function drush(string $command, array $args = [], array $options = [], ?string $site_specification = null, ?string $cd = null, int $expected_return = self::EXIT_SUCCESS, ?string $suffix = null, array $env = []): void
    {
        list($cmd, $coverage_file) = $this->prepareDrushCommand($command, $args, $options, $site_specification, $suffix, $cd);
        $env['COLUMNS'] = '9999';
        $this->execute($cmd, $expected_return, $cd, $env);

        // Save code coverage information.
        if (!empty($coverage_file)) {
            $data = unserialize(file_get_contents($coverage_file));
            unlink($coverage_file);
            // Save for appending after the test finishes.
            $this->coverage_data[] = $data;
        }

        // return $return;
    }

    protected function prepareDrushCommand(string $command, array $args = [], array $options = [], ?string $site_specification = null, ?string $suffix = null, ?string $cd = null): array
    {
        // cd is added for the benefit of siteSshTest which tests a strict command.
        $global_option_list = ['simulate', 'root', 'uri', 'include', 'config', 'alias-path', 'ssh-options', 'cd'];
        $options += ['uri' => 'dev']; // Default value.
        $hide_stderr = false;
        $drushExecutable = self::getDrush();
        if ($cd) {
            $project = dirname($cd);
            if (file_exists("$project/vendor/bin/drush")) {
                $drushExecutable = "$project/vendor/bin/drush";
            }
        }
        $cmd[] = $drushExecutable;

        // Insert global options.
        foreach ($options as $key => $values) {
            // Normalize to an array of values which is uncommon but is supported via
            // multiple instances of the same option.
            if (!is_iterable($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                $value = (string) $value;
                if (in_array($key, $global_option_list)) {
                    unset($options[$key]);
                    if ($key == 'uri' && $value == 'OMIT') {
                        continue;
                    }
                    $dashes = strlen($key) == 1 ? '-' : '--';
                    $equals = strlen($key) == 1 ? '' : '=';
                    if (!isset($value)) {
                        $cmd[] = "$dashes$key";
                    } else {
                        $cmd[] = "$dashes$key$equals" . self::escapeshellarg((string)$value);
                    }
                }
            }
        }

        if ($level = $this->logLevel()) {
            $cmd[] = '--' . $level;
        }
        $cmd[] = "--no-interaction";

        // Insert code coverage argument before command, in order for it to be
        // parsed as a global option. This matters for commands like ssh and rsync
        // where options after the command are passed along to external commands.
        $coverage_file = null;
//        $result = $this->getTestResultObject();
//        if ($result->getCollectCodeCoverageInformation()) {
//            $coverage_file = tempnam($this->getSandbox(), 'drush_coverage');
//            if ($coverage_file) {
//                $cmd[] = "--drush-coverage=" . $coverage_file;
//            }
//        }

        // Insert site specification and drush command.
        $cmd[] = empty($site_specification) ? null : self::escapeshellarg($site_specification);
        $cmd[] = $command;

        // Insert drush command arguments.
        foreach ($args as $arg) {
            // Cast because the CLI sends only strings.
            $cmd[] = self::escapeshellarg((string)$arg);
        }
        // insert drush command options
        foreach ($options as $key => $values) {
            // Normalize to an array of values which is uncommon but is supported via
            // multiple instances of the same option.
            if (!is_iterable($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                $dashes = strlen($key) == 1 ? '-' : '--';
                $equals = strlen($key) == 1 ? '' : '=';
                if (!isset($value)) {
                    $cmd[] = "$dashes$key";
                } else {
                    // Cast because the CLI sends only strings.
                    $cmd[] = "$dashes$key$equals" . self::escapeshellarg((string)$value);
                }
            }
        }

        $cmd[] = $suffix;
        if ($hide_stderr) {
            $cmd[] = '2>' . $this->bitBucket();
        }
        // Remove NULLs
        $exec = array_filter($cmd, fn ($value) => !is_null($value));
        $cmd = implode(' ', $exec);
        return [$cmd, $coverage_file];
    }

    protected function getLogMessage(array $entry): string
    {
        return $this->interpolate($entry['message'], $entry);
    }

    protected function interpolate(string $message, array $context): string
    {
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace[sprintf('{%s}', $key)] = $val;
            }
        }
        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    protected function assertOutputEquals(?string $expected, string $filter = ''): void
    {
        $output = $this->getSimplifiedOutput();
        if (!empty($filter)) {
            $output = preg_replace($filter, '', $output);
        }
        $this->assertEquals($expected, $output);
    }

    /**
     * Checks that the error output matches the expected output.
     *
     * This matches against a simplified version of the actual output that has
     * absolute paths and duplicate whitespace removed, to avoid false negatives
     * on minor differences.
     *
     * @param $expected
     *   The expected output.
     * @param $filter
     *   Optional regular expression that should be ignored in the error output.
     */
    protected function assertErrorOutputEquals(string $expected, string $filter = ''): void
    {
        $output = $this->getSimplifiedErrorOutput();
        if (!empty($filter)) {
            $output = preg_replace($filter, '', $output);
        }
        $this->assertEquals($expected, $output);
    }

    public function pathsToSimplify(): array
    {
        $basedir = dirname(dirname(__DIR__));

        return [

            self::getSandbox() => '__SANDBOX__',
            $basedir => '__DIR__',

        ];
    }
}
