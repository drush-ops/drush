<?php

namespace Unish;

use Unish\Controllers\RuntimeController;
use Unish\Utils\OutputUtilsTrait;

/**
 * UnishIntegrationTestCase will prepare a single Drupal site and
 * bootstrap it once.  All integration tests will run in this same bootstrapped
 * environment in the same phpunit process, and must not do anything to
 * damage or alter it.
 *
 * Note that it is a general limitation of Drupal that any one php process
 * may bootstrap Drupal at most once. Attempting to bootstrap Drupal twice
 * will lead to undefined behavior -- usually a fatal error from defining
 * the same constant more than once. The unish runtime controller is used
 * to ensure that only one bootstrap is done.
 */
abstract class UnishIntegrationTestCase extends UnishTestCase
{
    use OutputUtilsTrait;

    /**
     * @var string
     */
    protected $stdout = '';

    /**
     * @var string
     */
    protected $stderr = '';

    /**
     * {@inheritdoc}
     */
    public function getOutputRaw()
    {
        return $this->stdout;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorOutputRaw()
    {
        return $this->stderr;
    }

    /**
     * Invoke drush via a direct method call to Application::run().
     *
     * @param string $command
     *   A defined drush command such as 'cron', 'status' and so on
     * @param array $args
     *   Command arguments.
     * @param array $options
     *   An associative array containing options.
     * @param int $expected_return
     *   The expected exit code. Usually static::EXIT_ERROR or static::EXIT_SUCCESS.
     * @param string|bool $stdin
     *
     * @return int
     *   An exit code.
     */
    public function drush($command, array $args = [], array $options = [], $expected_return = self::EXIT_SUCCESS, $stdin = false)
    {
        // Install the SUT if necessary
        if (!RuntimeController::instance()->initialized()) {
            $this->checkInstallSut();
        }

        $cmd = $this->buildCommandLine($command, $args, $options);

        // Get the application instance from the runtime controller.
        $application = RuntimeController::instance()->application($this->webroot(), $cmd);

        // Get a reference to the input and output objects
        $input = RuntimeController::instance()->input();
        $output = RuntimeController::instance()->output();

        // Set up stdin if it was provided
        if ($stdin) {
            $this->setStdin($stdin);
        }

        // We only bootstrap the first time, and phpunit likes to reset the
        // cwd at the beginning of every test function. We therefore need to
        // change the working directory back to where Drupal expects it to be.
        chdir(static::webroot());
        $this->log("Executing: " . implode(' ', $cmd), 'verbose');
        $return = $application->run($input, $output);
        static::assertEquals($expected_return, $return, "Command failed: \n\n" . $this->getErrorOutput());

        $this->stdout = $output->fetch();
        $this->stderr = $output->getErrorOutput()->fetch();

        // Empty Drush's legacy context system
        $cache = &drush_get_context();
        $cache = [];

        return $return;
    }

    /**
     * @param string $contents
     */
    protected function setStdin($contents)
    {
        $path = $this->writeToTmpFile($contents);
        $stdinHandler = RuntimeController::instance()->stdinHandler();
        $stdinHandler->redirect($path);
    }

    /**
     * @return string[]
     */
    protected function getGlobalOptionNames()
    {
        return [
            'simulate',
            'root',
            'uri',
            'include',
            'config',
            'alias-path',
            'ssh-options',
            'backend',
            'cd',
        ];
    }

    /**
     * @return array
     */
    protected function getCommonCommandLineOptions()
    {
        return [
            'root' => static::webroot(),
            'uri' => static::INTEGRATION_TEST_ENV,
        ];
    }

    /**
     * @param string $command
     * @param array $args
     * @param array $options
     *
     * @return array
     */
    protected function buildCommandLine($command, $args, $options)
    {
        $logLevel = $this->logLevel();
        $globalOptionNames = array_flip($this->getGlobalOptionNames());

        $options += $this->getCommonCommandLineOptions();
        if (isset($options['uri']) && $options['uri'] === 'OMIT') {
            unset($options['uri']);
        }

        $globalOptions = array_intersect_key($options, $globalOptionNames);
        $options = array_diff_key($options, $globalOptionNames);

        $globalOptions['no-interaction'] = true;
        if (in_array($logLevel, ['debug', 'verbose'])) {
            $globalOptions[$logLevel] = true;
        }

        $cmd = [static::getDrush()];
        $this->buildCommandLineOptions($cmd, $globalOptions);
        $cmd = array_merge($cmd, [$command], $args);
        $this->buildCommandLineOptions($cmd, $options);

        return array_filter($cmd, 'strlen');
    }

    /**
     * @param array $cmd
     * @param array $options
     */
    protected function buildCommandLineOptions(&$cmd, $options)
    {
        foreach ($options as $name => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            foreach ($value as $v) {
                $cmd[] = "--$name" . ($v === null || $v === true ? '' : "=$v");
            }
        }
    }

    /**
     * @param string $expected
     * @param string $filter
     */
    protected function assertOutputEquals($expected, $filter = '')
    {
        $output = $this->getSimplifiedOutput();
        if (!empty($filter)) {
            $output = preg_replace($filter, '', $output);
        }

        static::assertEquals($expected, $output);
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

        static::assertEquals($expected, $output);
    }
}
