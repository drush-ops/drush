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
        // cd is added for the benefit of siteSshTest which tests a strict command.
        $global_option_list = ['simulate', 'root', 'uri', 'include', 'config', 'alias-path', 'ssh-options', 'backend', 'cd'];
        $options += ['uri' => 'dev']; // Default value.
        $hide_stderr = false;
        $cmd[] = self::getDrush();

        // Insert global options.
        foreach ($options as $key => $value) {
            if (in_array($key, $global_option_list)) {
                unset($options[$key]);
                if ($key == 'backend') {
                    $hide_stderr = true;
                    $value = null;
                }
                if ($key == 'uri' && $value == 'OMIT') {
                    continue;
                }
                if (!isset($value)) {
                    $cmd[] = "--$key";
                } else {
                    $cmd[] = "--$key=" . self::escapeshellarg($value);
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
            if (!isset($value)) {
                $cmd[] = "--$key";
            } else {
                $cmd[] = "--$key=" . self::escapeshellarg($value);
            }
        }

        $cmd[] = $suffix;
        if ($hide_stderr) {
            $cmd[] = '2>' . $this->bitBucket();
        }
        $exec = array_filter($cmd, 'strlen'); // Remove NULLs
        // Set sendmail_path to 'true' to disable any outgoing emails
        // that tests might cause Drupal to send.

        $cmd = implode(' ', $exec);
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

    /**
     * A slightly less functional copy of drush_backend_parse_output().
     */
    public function parseBackendOutput($string)
    {
        $regex = sprintf(self::getBackendOutputDelimiter(), '(.*)');
        preg_match("/$regex/s", $string, $match);
        if (isset($match[1])) {
            // we have our JSON encoded string
            $output = $match[1];
            // remove the match we just made and any non printing characters
            $string = trim(str_replace(sprintf(self::getBackendOutputDelimiter(), $match[1]), '', $string));
        }

        if (!empty($output)) {
            $data = json_decode($output, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return $string;
    }

    /**
     * Ensure that an expected log message appears in the Drush log.
     *
     *     $this->drush('command', array(), array('backend' => NULL));
     *     $parsed = $this->parse_backend_output($this->getOutput());
     *     $this->assertLogHasMessage($parsed['log'], "Expected message", 'debug')
     *
     * @param $log Parsed log entries from backend invoke
     * @param $message The expected message that must be contained in
     *   some log entry's 'message' field.  Substrings will match.
     * @param $logType The type of log message to look for; all other
     *   types are ignored. If FALSE (the default), then all log types
     *   will be searched.
     */
    public function assertLogHasMessage($log, $message, $logType = false)
    {
        foreach ($log as $entry) {
            if (!$logType || ($entry['type'] == $logType)) {
                $logMessage = $this->getLogMessage($entry);
                if (strpos($logMessage, $message) !== false) {
                    return true;
                }
            }
        }
        $this->fail("Could not find expected message in log: " . $message);
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
