<?php

declare(strict_types=1);

namespace Unish;

use Drush\TestTraits\OutputUtilsTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Unish\Controllers\RuntimeController;

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

    protected string $stdout = '';
    protected string $stderr = '';

    /**
     * @inheritdoc
     */
    public function getOutputRaw(): string
    {
        return $this->stdout;
    }

    /**
     * @inheritdoc
     */
    public function getErrorOutputRaw(): string
    {
        return $this->stderr;
    }

    /**
     * Invoke drush via a direct method call to Application::run().
     *
     * @param $command
     *   A defined drush command such as 'cron', 'status' and so on
     * @param $args
     *   Command arguments.
     * @param $options
     *   An associative array containing options.
     * @param $expected_return
     *   The expected exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
     * @param $stdin
     *   A string that will be written to a tmp file. Note that the command you
     *   are testing must implement StdinAwareInterface.
     */
    public function drush(string $command, array $args = [], array $options = [], ?int $expected_return = self::EXIT_SUCCESS, string|bool $stdin = false): ?int
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
        chdir($this->webroot());
        $this->log("Executing: " . implode(' ', $cmd), 'verbose');
        $return = $application->run($input, $output);
        $this->stdout = $output->fetch();
        $this->stderr = $output->getErrorOutput()->fetch();

        // Undo the env variable and verbosity property that Process unhelpfully persists.
        // https://github.com/symfony/console/blob/3.4/Application.php#L970-L972
        putenv('SHELL_VERBOSITY');
        unset($_ENV['SHELL_VERBOSITY'], $_SERVER['SHELL_VERBOSITY']);
        $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        $this->assertEquals($expected_return, $return, "Command failed: \n\n" . $this->getErrorOutput());
        return $return;
    }

    protected function setStdin($contents): void
    {
        $path = $this->writeToTmpFile($contents);
        $stdinHandler = RuntimeController::instance()->stdinHandler();
        $stdinHandler->redirect($path);
    }

    protected function buildCommandLine($command, $args, $options): array
    {
        $global_option_list = ['simulate', 'root', 'uri', 'include', 'config', 'alias-path', 'ssh-options', 'cd'];
        $options += ['root' => $this->webroot(), 'uri' => self::INTEGRATION_TEST_ENV]; // Default value.
        $cmd = [self::getDrush()];

        // Insert global options.
        foreach ($options as $key => $values) {
            // Normalize to an array of values which is uncommon but is supported via
            // multiple instances of the same option.
            if (!is_iterable($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
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
        }

        if ($level = $this->logLevel()) {
            $cmd[] = '--' . $level;
        }
        $cmd[] = "--no-interaction";

        $cmd[] = $command;

        // Insert drush command arguments.
        foreach ($args as $arg) {
            // Cast because on CLI all args are strings.
            $cmd[] = (string)$arg;
        }
        // insert drush command options
        foreach ($options as $key => $values) {
            // Normalize to an array of values which is uncommon but is supported via
            // multiple instances of the same option.
            if (!is_iterable($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                if (!isset($value) || $value === true) {
                    $cmd[] = "--$key";
                } else {
                    $cmd[] = "--$key=" . $value;
                }
            }
        }

        // Remove NULLs
        $cmd = array_filter($cmd, 'strlen');

        return $cmd;
    }

    protected function assertOutputEquals($expected, $filter = ''): void
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

    /**
     * Checks that the error output contains the expected output.
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
    protected function assertErrorOutputContains(string $expected, string $filter = ''): void
    {
        $output = $this->getSimplifiedErrorOutput();
        if (!empty($filter)) {
            $output = preg_replace($filter, '', $output);
        }
        $this->assertStringContainsString($expected, $output);
    }

    public function setupDrupal($num_sites = 1, $install = false, $options = []): array
    {
        throw new \Exception('Drupal is already setup in an integration test');
    }
}
