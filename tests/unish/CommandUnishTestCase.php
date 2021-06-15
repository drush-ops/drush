<?php

namespace Unish;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use PHPUnit\Framework\TestResult;
use Drush\TestTraits\OutputUtilsTrait;
use Drush\TestTraits\CliTestTrait;

abstract class CommandUnishTestCase extends UnishTestCase
{
    use CliTestTrait;

    /**
     * Code coverage data collected during a single test.
     *
     * @var array
     */
    protected $coverage_data = [];

    /**
     * Accessor for the last output, non-trimmed.
     *
     * @return string
     *   Raw output as text.
     *
     * @access public
     */
    public function getOutputRaw()
    {
        return $this->process ? $this->process->getOutput() : '';
    }

    /**
     * Accessor for the last stderr output, non-trimmed.
     *
     * @return string
     *   Raw stderr as text.
     *
     * @access public
     */
    public function getErrorOutputRaw()
    {
        return $this->process ? $this->process->getErrorOutput() : '';
    }

    /**
     * Invoke drush command via startExecute(), and return the resulting process.
     *
     * Use this method when you need to interact with the Drush command under
     * test while it is still running. Currently used to test 'watchdog:tail'.
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
    public function drushBackground($command, array $args = [], array $options = [], $site_specification = null, $cd = null, $suffix = null, $env = [])
    {
        list($cmd, ) = $this->prepareDrushCommand($command, $args, $options, $site_specification, $suffix);
        return $this->startExecute($cmd, $cd, $env);
    }

    /**
     * Invoke drush in via execute().
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
      * @param $expected_return
      *   The expected exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
      * @param $suffix
      *   Any code to append to the command. For example, redirection like 2>&1.
      * @param array $env
      *   Environment variables to pass along to the subprocess.
      * @return integer
      *   An exit code.
      */
    public function drush($command, array $args = [], array $options = [], $site_specification = null, $cd = null, $expected_return = self::EXIT_SUCCESS, $suffix = null, $env = [])
    {
        list($cmd, $coverage_file) = $this->prepareDrushCommand($command, $args, $options, $site_specification, $suffix);
        $return = $this->execute($cmd, $expected_return, $cd, $env);

        // Save code coverage information.
        if (!empty($coverage_file)) {
            $data = unserialize(file_get_contents($coverage_file));
            unlink($coverage_file);
            // Save for appending after the test finishes.
            $this->coverage_data[] = $data;
        }

        return $return;
    }

    protected function prepareDrushCommand($command, array $args = [], array $options = [], $site_specification = null, $suffix = null)
    {
        // cd is added for the benefit of siteSshTest which tests a strict command.
        $global_option_list = ['simulate', 'root', 'uri', 'include', 'config', 'alias-path', 'ssh-options', 'cd'];
        $options += ['uri' => 'dev']; // Default value.
        $hide_stderr = false;
        $cmd[] = self::getDrush();

        // Insert global options.
        foreach ($options as $key => $value) {
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
                    $cmd[] = "$dashes$key$equals" . self::escapeshellarg($value);
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
        $result = $this->getTestResultObject();
        if ($result->getCollectCodeCoverageInformation()) {
            $coverage_file = tempnam($this->getSandbox(), 'drush_coverage');
            if ($coverage_file) {
                $cmd[] = "--drush-coverage=" . $coverage_file;
            }
        }

        // Insert site specification and drush command.
        $cmd[] = empty($site_specification) ? null : self::escapeshellarg($site_specification);
        $cmd[] = $command;

        // Insert drush command arguments.
        foreach ($args as $arg) {
            $cmd[] = self::escapeshellarg($arg);
        }
        // insert drush command options
        foreach ($options as $key => $value) {
            $dashes = strlen($key) == 1 ? '-' : '--';
            $equals = strlen($key) == 1 ? '' : '=';
            if (!isset($value)) {
                $cmd[] = "$dashes$key";
            } else {
                $cmd[] = "$dashes$key$equals" . self::escapeshellarg($value);
            }
        }

        $cmd[] = $suffix;
        if ($hide_stderr) {
            $cmd[] = '2>' . $this->bitBucket();
        }
        $exec = array_filter($cmd, 'strlen'); // Remove NULLs
        $cmd = implode(' ', $exec);
        return [$cmd, $coverage_file];
    }

    protected function getLogMessage($entry)
    {
        return $this->interpolate($entry['message'], $entry);
    }

    protected function interpolate($message, array $context)
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

    protected function assertOutputEquals($expected, $filter = '')
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
     * @param string $expected
     *   The expected output.
     * @param string $filter
     *   Optional regular expression that should be ignored in the error output.
     */
    protected function assertErrorOutputEquals($expected, $filter = '')
    {
        $output = $this->getSimplifiedErrorOutput();
        if (!empty($filter)) {
            $output = preg_replace($filter, '', $output);
        }
        $this->assertEquals($expected, $output);
    }

    public function pathsToSimplify()
    {
        $basedir = dirname(dirname(__DIR__));

        return [

            self::getSandbox() => '__SANDBOX__',
            $basedir => '__DIR__',

        ];
    }
}
