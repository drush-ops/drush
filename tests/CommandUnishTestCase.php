<?php

namespace Unish;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Webmozart\PathUtil\Path;

abstract class CommandUnishTestCase extends UnishTestCase {

  // Unix exit codes.
    const EXIT_SUCCESS  = 0;
    const EXIT_ERROR = 1;
    const UNISH_EXITCODE_USER_ABORT = 75; // Same as DRUSH_EXITCODE_USER_ABORT

  /**
   * Code coverage data collected during a single test.
   *
   * @var array
   */
    protected $coverage_data = [];

  /**
   * Process of last executed command.
   *
   * @var Process
   */
    private $process;

  /**
   * Default timeout for commands.
   *
   * @var int
   */
    private $defaultTimeout = 60;

  /**
   * Timeout for command.
   *
   * Reset to $defaultTimeout after executing a command.
   *
   * @var int
   */
    protected $timeout = 60;

  /**
   * Default idle timeout for commands.
   *
   * @var int
   */
    private $defaultIdleTimeout = 15;

  /**
   * Idle timeouts for commands.
   *
   * Reset to $defaultIdleTimeout after executing a command.
   *
   * @var int
   */
    protected $idleTimeout = 15;

  /**
   * Get command output and simiplify away things like full paths and extra
   * whitespace.
   */
    protected function getSimplifiedOutput()
    {
        $output = $this->getOutput();
        // We do not care if Drush inserts a -t or not in the string. Depends on whether there is a tty.
        $output = preg_replace('# -t #', ' ', $output);
        // Remove double spaces from output to help protect test from false negatives if spacing changes subtlely
        $output = preg_replace('#  *#', ' ', $output);
        // Debug flags may be added to command strings if we are in debug mode. Take those out so that tests in phpunit --debug mode work
        $output = preg_replace('# --debug #', ' ', $output);
        $output = preg_replace('# --verbose #', ' ', $output);
        // Get rid of any full paths in the output
        $output = str_replace(__DIR__, '__DIR__', $output);
        $output = str_replace(self::getSandbox(), '__SANDBOX__', $output);
        $output = str_replace(self::getSut(), '__SUT__', $output);

        return $output;
    }

  /**
   * Accessor for the last output, trimmed.
   *
   * @return string
   *   Trimmed output as text.
   *
   * @access public
   */
    public function getOutput()
    {
        return trim($this->getOutputRaw());
    }

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
   * Accessor for the last output, rtrimmed and split on newlines.
   *
   * @return array
   *   Output as array of lines.
   *
   * @access public
   */
    public function getOutputAsList()
    {
        return array_map('rtrim', explode("\n", $this->getOutput()));
    }

  /**
   * Accessor for the last stderr output, trimmed.
   *
   * @return string
   *   Trimmed stderr as text.
   *
   * @access public
   */
    public function getErrorOutput()
    {
        return trim($this->getErrorOutputRaw());
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
   * Accessor for the last stderr output, rtrimmed and split on newlines.
   *
   * @return array
   *   Stderr as array of lines.
   *
   * @access public
   */
    public function getErrorOutputAsList()
    {
        return array_map('rtrim', explode("\n", $this->getErrorOutput()));
    }

  /**
   * Accessor for the last output, decoded from json.
   *
   * @param string $key
   *   Optionally return only a top level element from the json object.
   *
   * @return object
   *   Decoded object.
   */
    public function getOutputFromJSON($key = null)
    {
        $json = json_decode($this->getOutput());
        if (isset($key)) {
            $json = $json->{$key}; // http://stackoverflow.com/questions/2925044/hyphens-in-keys-of-object
        }
        return $json;
    }

  /**
   * Actually runs the command.
   *
   * @param string $command
   *   The actual command line to run.
   * @param integer $expected_return
   *   The return code to expect
   * @param sting cd
   *   The directory to run the command in.
   * @param array $env
   *  @todo: Not fully implemented yet. Inheriting environment is hard - http://stackoverflow.com/questions/3780866/why-is-my-env-empty.
   *         @see drush_env().
   *  Extra environment variables.
   * @param string $input
   *   A string representing the STDIN that is piped to the command.
   * @return integer
   *   Exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
   */
    public function execute($command, $expected_return = self::EXIT_SUCCESS, $cd = null, $env = null, $input = null)
    {
        $return = 1;
        $this->tick();

        // Apply the environment variables we need for our test to the head of the
        // command (excludes Windows). Process does have an $env argument, but it replaces the entire
        // environment with the one given. This *could* be used for ensuring the
        // test ran with a clean environment, but it also makes tests fail hard on
        // Travis, for unknown reasons.
        // @see https://github.com/drush-ops/drush/pull/646
        $prefix = '';
        if ($env && !$this->isWindows()) {
            foreach ($env as $env_name => $env_value) {
                $prefix .= $env_name . '=' . self::escapeshellarg($env_value) . ' ';
            }
        }
        $this->log("Executing: $command", 'verbose');

        try {
            // Process uses a default timeout of 60 seconds, set it to 0 (none).
            $this->process = new Process($command, $cd, null, $input, 0);
            if (!getenv('UNISH_NO_TIMEOUTS')) {
                $this->process->setTimeout($this->timeout)
                ->setIdleTimeout($this->idleTimeout);
            }
            $return = $this->process->run();
            if ($expected_return !== $return) {
                $message = 'Unexpected exit code ' . $return . ' (expected ' . $expected_return . ") for command:\n" .  $command;
                throw new UnishProcessFailedError($message, $this->process);
            }
            // Reset timeouts to default.
            $this->timeout = $this->defaultTimeout;
            $this->idleTimeout = $this->defaultIdleTimeout;
            return $return;
        } catch (ProcessTimedOutException $e) {
            if ($e->isGeneralTimeout()) {
                $message = 'Command runtime exceeded ' . $this->timeout . " seconds:\n" .  $command;
            } else {
                $message = 'Command had no output for ' . $this->idleTimeout . " seconds:\n" .  $command;
            }
            throw new UnishProcessFailedError($message, $this->process);
        }
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
   *   Environment variables to pass along to the subprocess. @todo - not used.
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
        $cmd[] = "--no-ansi";
        $cmd[] = "--no-interaction";

        // Insert code coverage argument before command, in order for it to be
        // parsed as a global option. This matters for commands like ssh and rsync
        // where options after the command are passed along to external commands.
        $result = $this->getTestResultObject();
        if ($result->getCollectCodeCoverageInformation()) {
            $coverage_file = tempnam(UNISH_TMP, 'drush_coverage');
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

        $php_options = (array_key_exists('PHP_OPTIONS', $env)) ? $env['PHP_OPTIONS'] . " " : "";
        // @todo The PHP Options below are not yet honored by execute(). See .travis.yml for an alternative way.
        $env['PHP_OPTIONS'] = "${php_options}-d sendmail_path='true'";
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
   * Override the run method, so we can add in our code coverage data after the
   * test has run.
   *
   * We have to collect all coverage data, merge them and append them as one, to
   * avoid having phpUnit duplicating the test function as many times as drush
   * has been invoked.
   *
   * Runs the test case and collects the results in a TestResult object.
   * If no TestResult object is passed a new one will be created.
   *
   * @param  \PHPUnit_Framework_TestResult $result
   * @return \PHPUnit_Framework_TestResult
   * @throws \PHPUnit_Framework_Exception
   */
    public function run(\PHPUnit_Framework_TestResult $result = null)
    {
        $result = parent::run($result);
        $data = [];
        foreach ($this->coverage_data as $merge_data) {
            foreach ($merge_data as $file => $lines) {
                if (!isset($data[$file])) {
                    $data[$file] = $lines;
                } else {
                    foreach ($lines as $num => $executed) {
                        if (!isset($data[$file][$num])) {
                            $data[$file][$num] = $executed;
                        } else {
                            $data[$file][$num] = ($executed == 1 ? $executed : $data[$file][$num]);
                        }
                    }
                }
            }
        }

        // Reset coverage data.
        $this->coverage_data = [];
        if (!empty($data)) {
            $result->getCodeCoverage()->append($data, $this);
        }
        return $result;
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

    public function drushMajorVersion()
    {
        static $major;

        if (!isset($major)) {
            $this->drush('version', [], ['field' => 'drush-version']);
            $version = trim($this->getOutput());
            list($major) = explode('.', $version);
        }
        return (int)$major;
    }

    protected function assertOutputEquals($expected, $filter = '')
    {
        $output = $this->getSimplifiedOutput();
        if (!empty($filter)) {
            $output = preg_replace($filter, '', $output);
        }
        $this->assertEquals($expected, $output);
    }
}
