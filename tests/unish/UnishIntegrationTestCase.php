<?php
namespace Unish;

use Drush\Config\Environment;
use Drush\Drush;
use Drush\Preflight\Preflight;
use Drush\Runtime\DependencyInjection;
use Drush\Runtime\Runtime;
use Drush\Symfony\LessStrictArgvInput;
use PHPUnit\Framework\TestResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Unish\Controllers\RuntimeController;
use Unish\Utils\OutputUtilsTrait;
use Webmozart\PathUtil\Path;

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

    protected $stdout = '';
    protected $stderr = '';

    /**
     * @inheritdoc
     */
    public function getOutputRaw()
    {
        return $this->stdout;
    }

    /**
     * @inheritdoc
     */
    public function getErrorOutputRaw()
    {
        return $this->stderr;
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
     * @param $expected_return
     *   The expected exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
     * @return integer
     *   An exit code.
     */
    public function drush($command, array $args = [], array $options = [], $expected_return = self::EXIT_SUCCESS)
    {
        // Install the SUT if necessary
        if (!RuntimeController::instance()->initialized()) {
            $this->checkInstallSut($this->webroot());
        }

        $cmd = $this->buildCommandLine($command, $args, $options);

        // Set up our input and output objects
        $input = new LessStrictArgvInput($cmd);
        $output = RuntimeController::instance()->output();

        // Get the application instance from the runtime controller.
        $application = RuntimeController::instance()->application($this->webroot(), self::INTEGRATION_TEST_ENV);

        // We only bootstrap the first time, and phpunit likes to reset the
        // cwd at the beginning of every test function. We therefore need to
        // change the working directory back to where Drupal expects it to be.
        chdir($this->webroot());
        $return = $application->run($input, $output);

        $this->stdout = $output->fetch();
        $this->stderr = $output->getErrorOutput()->fetch();

        // Empty Drush's legacy context system
        $cache = &drush_get_context();
        $cache = [];

        $this->assertEquals($expected_return, $return, "Command failed: \n\n" . $this->getErrorOutput());

        return $return;
    }

    protected function buildCommandLine($command, $args, $options)
    {
        $global_option_list = ['simulate', 'root', 'uri', 'include', 'config', 'alias-path', 'ssh-options', 'backend', 'cd'];
        $options += ['uri' => 'dev']; // Default value.
        $cmd = [self::getDrush()];

        // Insert global options.
        foreach ($options as $key => $value) {
            if (in_array($key, $global_option_list)) {
                unset($options[$key]);
                if ($key == 'uri' && $value == 'OMIT') {
                    continue;
                }
                if (!isset($value)) {
                    $cmd[] = "--$key";
                } else {
                    $cmd[] = "--$key=" . $value;
                }
            }
        }

        if ($level = $this->logLevel()) {
            $cmd[] = '--' . $level;
        }
        $cmd[] = "--no-interaction";

        $cmd[] = $command;

        // Insert drush command arguments.
        foreach ($args as $arg) {
            $cmd[] = $arg;
        }
        // insert drush command options
        foreach ($options as $key => $value) {
            if (!isset($value)) {
                $cmd[] = "--$key";
            } else {
                $cmd[] = "--$key=" . $value;
            }
        }

        // Remove NULLs
        $cmd = array_filter($cmd, 'strlen');

        return $cmd;
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
