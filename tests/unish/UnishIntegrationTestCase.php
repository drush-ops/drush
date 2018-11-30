<?php
namespace Unish;

use Drush\Config\Environment;
use Drush\Drush;
use Drush\Preflight\Preflight;
use Drush\Runtime\DependencyInjection;
use Drush\Runtime\Runtime;
use PHPUnit\Framework\TestResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
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

    protected $buffer = false;

    /**
     * @inheritdoc
     */
    public function getOutputRaw()
    {
        if (!$this->buffer) {
            return '';
        }
        return $this->buffer->fetch();
    }

    /**
     * @inheritdoc
     */
    public function getErrorOutputRaw()
    {
        if (!$this->buffer) {
            return '';
        }
        return $this->buffer->getErrorOutput()->fetch();
    }

    protected function initializeRuntime($cd, $env)
    {
        $loader = require PHPUNIT_COMPOSER_INSTALL;
        $environment = new Environment(Path::getHomeDirectory(), $cd ?: $this->webroot(), PHPUNIT_COMPOSER_INSTALL);
        $environment->setConfigFileVariant(Drush::getMajorVersion());
        $environment->setLoader($loader);
        $environment->applyEnvironment();
        $preflight = new Preflight($environment);
        $di = new DependencyInjection();
        $runtime = new Runtime($preflight, $di);

        return $runtime;
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
    public function drush($command, array $args = [], array $options = [], $site_specification = null, $cd = null, $expected_return = self::EXIT_SUCCESS, $suffix = null, $env = [])
    {
        // Flag invalid test parameters.
        $this->assertTrue(empty($suffix), '$suffix not supported for integration tests.');

        $cmd = $this->buildCommandLine($command, $args, $options, $site_specification);

        // Erase whatever cached content existed previously
        $this->buffer = new \Drush\Symfony\BufferedConsoleOutput();

        // Set up the runtime object and execute the command.
        $runtime = $this->initializeRuntime($cd, $env);
        $return = $runtime->execute($cmd, $this->buffer);

        // Empty Drush's legacy context system
        $cache = &drush_get_context();
        $cache = [];

        $this->assertEquals($expected_return, $return);
        return $return;
    }

    protected function buildCommandLine($command, $args, $options, $site_specification)
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

        // Insert site specification and drush command.
        $cmd[] = empty($site_specification) ? null : $site_specification;
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
