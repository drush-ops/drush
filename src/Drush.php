<?php

/**
 * @file
 * Contains \Drush.
 */
namespace Drush;

use Consolidation\SiteAlias\SiteAliasInterface;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteProcess\ProcessBase;
use Consolidation\SiteProcess\SiteProcess;
use League\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// TODO: Not sure if we should have a reference to PreflightArgs here.
// Maybe these constants should be in config, and PreflightArgs can
// reference them from there as well.
use Drush\Preflight\PreflightArgs;
use Symfony\Component\Process\Process;

/**
 * Static Service Container wrapper.
 *
 * This code is analogous to the \Drupal class in Drupal 8.
 *
 * We would like to move Drush towards the model of using constructor
 * injection rather than globals. This class serves as a unified global
 * accessor to arbitrary services for use by legacy Drush code.
 *
 * Advice from Drupal 8's 'Drupal' class:
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
     * The version of Drush from the drush.info file, or FALSE if not read yet.
     *
     * @var string|FALSE
     */
    protected static $version = false;
    protected static $majorVersion = false;
    protected static $minorVersion = false;

    /**
     * The Robo Runner -- manages and constructs all commandfile classes
     *
     * @var \Robo\Runner
     */
    protected static $runner;

    /**
     * Number of seconds before timeout for subprocesses. Can be customized via setTimeout() method.
     *
     * @var int
     */
    const TIMEOUT = 14400;

    /**
     * @return int
     */
    public static function getTimeout()
    {
        return self::TIMEOUT;
    }

    /**
     * Return the current Drush version.
     *
     * n.b. Called before the DI container is initialized.
     * Do not log, etc. here.
     */
    public static function getVersion()
    {
        if (!self::$version) {
            $drush_info = self::drushReadDrushInfo();
            self::$version = $drush_info['drush_version'];
        }
        return self::$version;
    }

    public static function getMajorVersion()
    {
        if (!self::$majorVersion) {
            $drush_version = self::getVersion();
            $version_parts = explode('.', $drush_version);
            self::$majorVersion = $version_parts[0];
        }
        return self::$majorVersion;
    }

    public static function getMinorVersion()
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
     *
     * @param \League\Container\Container $container
     *   A new container instance to replace the current.
     */
    public static function setContainer(ContainerInterface $container)
    {
        \Robo\Robo::setContainer($container);
    }

    /**
     * Unsets the global container.
     */
    public static function unsetContainer()
    {
        \Robo\Robo::unsetContainer();
    }

    /**
     * Returns the currently active global container.
     *
     * @return \League\Container\ContainerInterface|null
     *
     * @throws RuntimeException
     */
    public static function getContainer()
    {
        if (!\Robo\Robo::hasContainer()) {
            debug_print_backtrace();
            throw new \RuntimeException('Drush::$container is not initialized yet. \Drush::setContainer() must be called with a real container.');
        }
        return \Robo\Robo::getContainer();
    }

    /**
     * Returns TRUE if the container has been initialized, FALSE otherwise.
     *
     * @return bool
     */
    public static function hasContainer()
    {
        return \Robo\Robo::hasContainer();
    }

    /**
     * Get the current Symfony Console Application.
     *
     * @return Application
     */
    public static function getApplication()
    {
        return self::getContainer()->get('application');
    }

    /**
     * Return the Robo runner.
     *
     * @return \Robo\Runner
     */
    public static function runner()
    {
        if (!isset(self::$runner)) {
            self::$runner = new \Robo\Runner();
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
     *
     * @return mixed
     *   The specified service.
     */
    public static function service($id)
    {
        return self::getContainer()->get($id);
    }

    /**
     * Indicates if a service is defined in the container.
     *
     * @param string $id
     *   The ID of the service to check.
     *
     * @return bool
     *   TRUE if the specified service exists, FALSE otherwise.
     */
    public static function hasService($id)
    {
        // Check hasContainer() first in order to always return a Boolean.
        return self::hasContainer() && self::getContainer()->has($id);
    }

    /**
     * Return command factory
     *
     * @return \Consolidation\AnnotatedCommand\AnnotatedCommandFactory
     */
    public static function commandFactory()
    {
        return self::service('commandFactory');
    }

    /**
     * Return the Drush logger object.
     *
     * @return LoggerInterface
     *
     * @deprecated Use injected logger instead.
     */
    public static function logger()
    {
        return self::service('logger');
    }

    /**
     * Return the configuration object
     *
     * @return \Drush\Config\DrushConfig
     *
     * @deprecated Use injected configuration instead.
     */
    public static function config()
    {
        return self::service('config');
    }

    /**
     * @return SiteAliasManager
     *
     * @deprecated Use injected alias manager instead. @see Drush::drush()
     */
    public static function aliasManager()
    {
        return self::service('site.alias.manager');
    }

    /**
     * @return ProcessManager
     *
     * @deprecated Use injected process manager instead. @see Drush::drush()
     */
    public static function processManager()
    {
        return self::service('process.manager');
    }

    /**
     * Return the input object
     *
     * @return InputInterface
     */
    public static function input()
    {
        return self::service('input');
    }

    /**
     * Return the output object
     *
     * @return OutputInterface
     */
    public static function output()
    {
        return self::service('output');
    }

    /**
     * Run a Drush command on a site alias (or @self).
     *
     * Tip: Use injected process manager instead of this method. See below.
     *
     * A class should use ProcessManagerAwareInterface / ProcessManagerAwareTrait
     * in order to have the Process Manager injected by Drush's DI container.
     * For example:
     *
     *     use Consolidation\SiteProcess\ProcessManagerAwareTrait;
     *     use Consolidation\SiteProcess\ProcessManagerAwareInterface;
     *
     *     abstract class DrushCommands implements ProcessManagerAwareInterface ...
     *     {
     *         use ProcessManagerAwareTrait;
     *     }
     *
     * Since DrushCommands already uses ProcessManagerAwareTrait, all Drush
     * commands may use the process manager to call other Drush commands.
     * Other classes will need to ensure that the process manager is injected
     * as shown above.
     *
     * Note, however, that an alias record is required to use the `drush` method.
     * The alias manager will provide an alias record, but the alias manager is
     * not injected by default into Drush commands. In order to use it, it is
     * necessary to use SiteAliasManagerAwareTrait:
     *
     *     use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
     *     use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
     *
     *     class SiteInstallCommands extends DrushCommands implements SiteAliasManagerAwareInterface
     *     {
     *         use SiteAliasManagerAwareTrait;
     *
     *         public function install(array $profile, ...)
     *         {
     *             $selfRecord = $this->siteAliasManager()->getSelf();
     *             $args = ['system.site', ...];
     *             $options = ['yes' => true];
     *             $process = $this->processManager()->drush(selfRecord, 'config-set', $args, $options);
     *             $process->mustRun();
     *         }
     *     }
     *
     * Objects that are fetched from the DI container, or any Drush command will
     * automatically be given a reference to the alias manager if SiteAliasManagerAwareTrait
     * is used. Other objects will need to be manually provided with a reference
     * to the alias manager once it is created (call $obj->setAliasManager($aliasManager);).
     *
     * Clients that are using Drush::drush(), and need a reference to the alias
     * manager may use Drush::aliasManager().
     *
     * @param SiteAliasInterface $siteAlias
     * @param string $command
     * @param array $args
     * @param array $options
     * @param array $options_double_dash
     * @return SiteProcess
     */
    public static function drush(SiteAliasInterface $siteAlias, $command, $args = [], $options = [], $options_double_dash = [])
    {
        return self::processManager()->drush($siteAlias, $command, $args, $options, $options_double_dash);
    }

    /**
     * Run a bash fragment on a site alias. U
     *
     * Use Drush::drush() instead of this method when calling Drush.
     * Tip: Consider using injected process manager instead of this method. @see \Drush\Drush::drush().
     *
     * @param SiteAliasInterface $siteAlias
     * @param array $args
     * @param array $options
     * @param array $options_double_dash
     * @return ProcessBase
     */
    public static function siteProcess(SiteAliasInterface $siteAlias, $args = [], $options = [], $options_double_dash = [])
    {
        return self::processManager()->siteProcess($siteAlias, $args, $options, $options_double_dash);
    }

    /**
     * Run a bash fragment locally.
     *
     * The timeout parameter on this method doesn't work. It exists for compatibility with parent.
     * Call this method to get a Process and then call setters as needed.
     *
     * Tip: Consider using injected process manager instead of this method. @see \Drush\Drush::drush().
     *
     * @param string|array   $commandline The command line to run
     * @param string|null    $cwd         The working directory or null to use the working dir of the current PHP process
     * @param array|null     $env         The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null     $input       The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout     The timeout in seconds or null to disable
     * @param array          $options     An array of options for proc_open
     *
     * @return ProcessBase
     *   A wrapper around Symfony Process.
     */
    public static function process($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        return self::processManager()->process($commandline, $cwd, $env, $input, $timeout);
    }

    /**
     * Create a Process instance from a commandline string.
     *
     * Tip: Consider using injected process manager instead of this method. @see \Drush\Drush::drush().
     *
     * @param string $command The commandline string to run
     * @param string|null $cwd     The working directory or null to use the working dir of the current PHP process
     * @param array|null $env     The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null $input   The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout The timeout in seconds or null to disable
     * @return Process
     */
    public static function shell($command, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        return self::processManager()->shell($command, $cwd, $env, $input, $timeout);
    }

    /**
     * Return 'true' if we are in simulated mode
     *
     * @deprecated Inject configuration and use $this->getConfig()->simulate().
     */
    public static function simulate()
    {
        return \Drush\Drush::config()->simulate();
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
    public static function verbose()
    {
        if (!self::hasService('output')) {
            return false;
        }
        return \Drush\Drush::output()->isVerbose();
    }

    /**
     * Return 'true' if we are in debug mode
     */
    public static function debug()
    {
        if (!self::hasService('output')) {
            return false;
        }
        return \Drush\Drush::output()->isDebug();
    }

    /**
     * Return the Bootstrap Manager.
     *
     * @return \Drush\Boot\BootstrapManager
     */
    public static function bootstrapManager()
    {
        return self::service('bootstrap.manager');
    }

    /**
     * Return the Bootstrap object.
     *
     * @return \Drush\Boot\Boot
     */
    public static function bootstrap()
    {
        return self::bootstrapManager()->bootstrap();
    }

    public static function redispatchOptions($input = null)
    {
        $input = $input ?: self::input();

        // $input->getOptions() returns an associative array of option => value
        $options = $input->getOptions();

        // The 'runtime.options' config contains a list of option names on th cli
        $optionNamesFromCommandline = self::config()->get('runtime.options');

        // Remove anything in $options that was not on the cli
        $options = array_intersect_key($options, array_flip($optionNamesFromCommandline));

        // Add in the 'runtime.context' items, which includes --include, --alias-path et. al.
        return $options + array_filter(self::config()->get(PreflightArgs::DRUSH_RUNTIME_CONTEXT_NAMESPACE));
    }

    /**
     * Read the drush info file.
     */
    private static function drushReadDrushInfo()
    {
        $drush_info_file = dirname(__FILE__) . '/../drush.info';

        return parse_ini_file($drush_info_file);
    }
}
