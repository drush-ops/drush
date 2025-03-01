<?php

declare(strict_types=1);

namespace Drush;

use Composer\InstalledVersions;
use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Consolidation\SiteAlias\SiteAliasInterface;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteProcess\ProcessBase;
use Consolidation\SiteProcess\SiteProcess;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBoot8;
use Drush\Config\DrushConfig;
use Drush\Preflight\PreflightArgs;
use Drush\Runtime\DependencyInjection;
use Drush\SiteAlias\ProcessManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Robo\Robo;
use Robo\Runner;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// TODO: Not sure if we should have a reference to PreflightArgs here.
// Maybe these constants should be in config, and PreflightArgs can
// reference them from there as well.

/**
 * Static Service Container wrapper.
 *
 * This code is analogous to the \Drupal class.
 *
 * We would like to move Drush towards the model of using constructor
 * injection rather than globals. This class serves as a unified global
 * accessor to arbitrary services for use by legacy Drush code.
 *
 * Advice from Drupal's 'Drupal' class:
 *
 * This class exists only to support legacy code that cannot be dependency
 * injected. If your code needs it, consider refactoring it to be object
 * oriented, if possible. When this is not possible, and your code is more
 * than a few non-reusable lines, it is recommended to instantiate an object
 * implementing the actual logic.
 *
 */
class Drush
{
    /**
     * The version of Drush from Composer Runtime, or FALSE if not populated yet.
     */
    protected static string|false $version = false;
    protected static string|false $majorVersion = false;
    protected static string|false $minorVersion = false;

    /**
     * The Robo Runner -- manages and constructs all commandfile classes
     */
    protected static Runner $runner;

    /**
     * Number of seconds before timeout for subprocesses. Can be customized via setTimeout() method.
     *
     * @var int
     */
    protected const TIMEOUT = 14400;

    public static function getTimeout(): int
    {
        return self::TIMEOUT;
    }

    /**
     * Return the current Drush version.
     *
     * n.b. Called before the DI container is initialized.
     * Do not log, etc. here.
     */
    public static function getVersion(): string|false
    {
        if (!self::$version) {
            self::$version = InstalledVersions::getVersion('drush/drush');
        }
        return self::$version;
    }

    /**
     * Convert internal Composer dev version to ".x"
     */
    public static function sanitizeVersionString($version): string
    {
        return preg_replace('#\.9+\.9+\.9+#', '.x', $version);
    }

    public static function getMajorVersion(): string
    {
        if (!self::$majorVersion) {
            $drush_version = self::getVersion();
            $version_parts = explode('.', $drush_version);
            self::$majorVersion = $version_parts[0];
        }
        return self::$majorVersion;
    }

    public static function getMinorVersion(): string
    {
        if (!self::$minorVersion) {
            $drush_version = self::getVersion();
            $version_parts = explode('.', $drush_version);
            self::$minorVersion = $version_parts[1];
        }
        return self::$minorVersion;
    }

    /**
     * Sets a new global container.
     */
    public static function setContainer($container): void
    {
        Robo::setContainer($container);
    }

    /**
     * Unsets the global container.
     */
    public static function unsetContainer(): void
    {
        Robo::unsetContainer();
    }

    /**
     * Returns the currently active global container.
     *
     * @throws RuntimeException
     */
    public static function getContainer(): ContainerInterface
    {
        if (!Robo::hasContainer()) {
            throw new RuntimeException('Drush::$container is not initialized yet. \Drush::setContainer() must be called with a real container.');
        }
        return Robo::getContainer();
    }

    /**
     * Returns TRUE if the container has been initialized, FALSE otherwise.
     */
    public static function hasContainer(): bool
    {
        return Robo::hasContainer();
    }

    /**
     * Get the current Symfony Console Application.
     */
    public static function getApplication(): Application
    {
        return self::getContainer()->get('application');
    }

    /**
     * Return the Robo runner.
     */
    public static function runner(): Runner
    {
        if (!isset(self::$runner)) {
            self::$runner = new Runner();
        }
        return self::$runner;
    }

    /**
     * Retrieves a service from the container.
     *
     * Use this method if the desired service is not one of those with a dedicated
     * accessor method below. If it is listed below, those methods are preferred
     * as they can return useful type hints.
     *
     * @param string $id
     *   The ID of the service to retrieve.
     */
    public static function service(string $id)
    {
        return self::getContainer()->get($id);
    }

    /**
     * Indicates if a service is defined in the container.
     */
    public static function hasService(string $id): bool
    {
        // Check hasContainer() first in order to always return a Boolean.
        return self::hasContainer() && self::getContainer()->has($id);
    }

    /**
     * Return command factory
     */
    public static function commandFactory(): AnnotatedCommandFactory
    {
        return self::service('commandFactory');
    }

    /**
     * Return the Drush logger object.
     *
     * @internal Commands should use $this->logger() instead.
     */
    public static function logger(): LoggerInterface
    {
        return self::service('logger');
    }

    /**
     * Return the configuration object
     *
     * @internal Commands should use $this->config() instead.
     */
    public static function config(): DrushConfig
    {
        return self::service('config');
    }

    /**
     * @internal Commands should use $this->siteAliasManager() instead.
     */
    public static function aliasManager(): SiteAliasManager
    {
        return self::service(DependencyInjection::SITE_ALIAS_MANAGER);
    }

    /**
     * @internal Commands should use $this->processManager() instead.
     */
    public static function processManager(): ProcessManager
    {
        return self::service('process.manager');
    }

    /**
     * Return the input object
     */
    public static function input(): InputInterface
    {
        return self::service('input');
    }

    /**
     * Return the output object
     */
    public static function output(): OutputInterface
    {
        return self::service('output');
    }

    /**
     * Run a Drush command on a site alias (or @self).
     *
     * Tip: Use injected processManager() instead of this method. See below.
     *
     * A class should use ProcessManagerAwareInterface / ProcessManagerAwareTrait
     * in order to have the Process Manager injected by Drush's DI container.
     * For example:
     * <code>
     *     use Consolidation\SiteProcess\ProcessManagerAwareTrait;
     *     use Consolidation\SiteProcess\ProcessManagerAwareInterface;
     *
     *     abstract class DrushCommands implements ProcessManagerAwareInterface ...
     *     {
     *         use ProcessManagerAwareTrait;
     *     }
     * </code>
     * Since DrushCommands already uses ProcessManagerAwareTrait, all Drush
     * commands may use the process manager to call other Drush commands.
     * Other classes will need to ensure that the process manager is injected
     * as shown above.
     *
     * Note, however, that an alias record is required to use the `drush` method.
     * Dependency inject the site alias manager to get an alias record.
     *
     * Clients that are using Drush::drush(), and need a reference to the alias
     * manager may use Drush::aliasManager().
     *
     */
    public static function drush(SiteAliasInterface $siteAlias, string $command, array $args = [], array $options = [], array $options_double_dash = []): SiteProcess
    {
        $return = self::processManager()->drush($siteAlias, $command, $args, $options, $options_double_dash);
        assert($return instanceof SiteProcess);
        return $return;
    }

    /**
     * Run a bash fragment on a site alias.
     *
     * Use \Drush\Drush::drush() instead of this method when calling Drush.
     *
     * Tip: Commands can consider using $this->processManager() instead of this method.
     */
    public static function siteProcess(SiteAliasInterface $siteAlias, array $args = [], array $options = [], array $options_double_dash = []): ProcessBase
    {
        return self::processManager()->siteProcess($siteAlias, $args, $options, $options_double_dash);
    }

    /**
     * Run a bash fragment locally.
     *
     * The timeout parameter on this method doesn't work. It exists for compatibility with parent.
     * Call this method to get a Process and then call setters as needed.
     *
     * Tip: Commandfiles should use processmanager() instead of this method.
     *
     * @param $commandline The command line to run
     * @param $cwd         The working directory or null to use the working dir of the current PHP process
     * @param $env         The environment variables or null to use the same environment as the current PHP process
     * @param $input       The input as stream resource, scalar or \Traversable, or null for no input
     * @param $timeout     The timeout in seconds or null to disable
     */
    public static function process(string|array $commandline, ?string $cwd = null, ?array $env = null, mixed $input = null, int|float|null $timeout = 60): ProcessBase
    {
        return self::processManager()->process($commandline, $cwd, $env, $input, $timeout);
    }

    /**
     * Create a Process instance from a commandline string.
     *
     * Tip: Consider using injected process manager instead of this method.
     *
     * @param string $command The commandline string to run
     * @param string|null $cwd     The working directory or null to use the working dir of the current PHP process
     * @param array|null $env     The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null $input   The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout The timeout in seconds or null to disable
     *
     * @return ProcessBase
     *   A wrapper around Symfony Process.
     */
    public static function shell(string $command, ?string $cwd = null, ?array $env = null, mixed $input = null, int|float|null $timeout = 60): ProcessBase
    {
        return self::processManager()->shell($command, $cwd, $env, $input, $timeout);
    }

    /**
     * Return 'true' if we are in simulated mode
     *
     * @internal Commands should use $this->getConfig()->simulate().
     */
    public static function simulate()
    {
        return Drush::config()->simulate();
    }

    /**
     * Return 'true' if we are in affirmative mode
     */
    public static function affirmative()
    {
        if (!self::hasService('input')) {
            throw new \Exception('No input service available.');
        }
        return Drush::input()->getOption('yes');
    }

    /**
     * Return 'true' if we are in negative mode
     */
    public static function negative()
    {
        if (!self::hasService('input')) {
            throw new \Exception('No input service available.');
        }
        return Drush::input()->getOption('no');
    }

    /**
     * Return 'true' if we are in verbose mode
     */
    public static function verbose(): bool
    {
        if (!self::hasService('output')) {
            return false;
        }
        return Drush::output()->isVerbose();
    }

    /**
     * Return 'true' if we are in debug mode
     */
    public static function debug(): bool
    {
        if (!self::hasService('output')) {
            return false;
        }
        return Drush::output()->isDebug();
    }

    /**
     * Return the Bootstrap Manager.
     */
    public static function bootstrapManager(): BootstrapManager
    {
        return self::service('bootstrap.manager');
    }

    /**
     * Return the Bootstrap object.
     */
    public static function bootstrap(): DrupalBoot8
    {
        return self::bootstrapManager()->bootstrap();
    }

    public static function redispatchOptions($input = null): array
    {
        $input = $input ?: self::input();
        $command_name = $input->getFirstArgument();

        // $input->getOptions() returns an associative array of option => value
        $options = $input->getOptions();

        // The 'runtime.options' config contains a list of option names on the CLI
        $optionNamesFromCommandline = self::config()->get('runtime.options');

        // Attempt to normalize option names.
        foreach ($optionNamesFromCommandline as $key => $name) {
            try {
                // Don't incorrectly remap these to --verbose, or discard them.
                if ($name == 'vv' || $name == 'vvv') {
                    // Special handling is in \Consolidation\SiteProcess\Util\ArgumentProcessor::convertOptions
                    $options[$name] = true;
                    continue;
                }
                $optionNamesFromCommandline[$key] = Drush::getApplication()->get($command_name)->getDefinition()->shortcutToName($name);
            } catch (InvalidArgumentException $e) {
                // Do nothing. It's expected.
            }
        }

        // Remove anything in $options that was not on the cli
        $options = array_intersect_key($options, array_flip($optionNamesFromCommandline));

        // Don't suppress output as it is usually needed in redispatches. See https://github.com/drush-ops/drush/issues/4805 and https://github.com/drush-ops/drush/issues/4933
        unset($options['quiet']);

        // Add in the 'runtime.context' items, which includes --include, --alias-path et. al.
        return $options + array_filter(self::config()->get(PreflightArgs::DRUSH_RUNTIME_CONTEXT_NAMESPACE));
    }
}
