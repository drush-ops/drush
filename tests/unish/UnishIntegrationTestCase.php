
<?php

namespace Unish;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use PHPUnit\Framework\TestResult;
use Unish\Utils\OutputUtilsTrait;

/**
 * UnishIntegrationTestCase will prepare a pair of Drupal multisites,
 * 'dev' and 'stage', and bootstrap Drupal ONCE.  All integration tests
 * will run in this same bootstrapped environment in the same phpunit
 * process, and must not do anything to damage or alter it.
 *
 * Note that each php process can bootstrap Drupal at most one time; attempting
 * to bootstrap Drupal twice may lead to undefined behavior. Bootstrapping
 * two different versions of Drupal in the same process will almost certainly
 * crash.
 */
abstract class UnishIntegrationTestCase extends UnishTestCase
{
    use OutputUtilsTrait;


    /**
     * @inheritdoc
     */
    public function getOutputRaw()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getErrorOutputRaw()
    {
        return '';
    }

    /**
     * Invoke drush via a direct method call to Application::run().
     *
     * @param command
     *   A defined drush command such as 'cron', 'status' and so on
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
     * @return integer
     *   An exit code.
     */
    public function drush($command, array $args = [], array $options = [], $site_specification = null, $cd = null, $expected_return = self::EXIT_SUCCESS)
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
}
