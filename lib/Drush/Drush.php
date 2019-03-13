<?php

/**
 * @file
 * Contains \Drush.
 */
namespace Drush;

use Consolidation\SiteAlias\SiteAliasInterface;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\ProcessBase;
use Consolidation\SiteProcess\SiteProcess;
use Drush\Command\DrushOutputAdapter;
use Drush\ConfigAdapter;
use Drush\DrushConfig;
use Drush\SiteAlias\ProcessManager;
use Drush\SiteAlias\AliasManagerAdapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Static Service provider.
 *
 * This is a back-port of the \Drush\Drush class from Drush 9.
 * Drush 8 does not use a dependency injection container; however,
 * some objects are injected into command files, and these should
 * be used preferentially whenever possible.
 *
 * @endcode
 */
class Drush
{
    protected static $config = null;
    protected static $aliasManager = null;
    protected static $processManager = null;
    protected static $input = null;
    protected static $output = null;
    protected static $drushVersion = null;
    protected static $drushMajorVersion = null;
    protected static $drushMinorVersion = null;

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
     */
    public static function getVersion()
    {
        if (!isset(self::$drushVersion)) {
            $drush_info = self::ReadDrushInfo();
            self::$drushVersion = $drush_info['drush_version'];
        }

        return self::$drushVersion;
    }

    /**
     * Return the Drush major version, e.g. 8, 9 or 10
     */
    public static function getMajorVersion()
    {
        if (!isset(self::$drushMajorVersion)) {
            $version_parts = explode('.', self::getVersion());
            self::$drushMajorVersion = $version_parts[0];
        }

        return self::$drushMajorVersion;
    }

    /**
     * Return the Drush minor version, e.g. the minor version of
     * Drush 9.5.2 is "5".
     */
    public static function getMinorVersion()
    {
        if (!isset(self::$drushMinorVersion)) {
            $version_parts = explode('.', self::getVersion());
            self::$drushMinorVersion = $version_parts[1];
        }

        return self::$drushMinorVersion;
    }

    /**
     * Read the drush info file.
     */
    public static function ReadDrushInfo()
    {
        $drush_info_file = dirname(dirname(__DIR__)) . '/drush.info';

        return parse_ini_file($drush_info_file);
    }

    // public static function setContainer(ContainerInterface $container)

    // public static function unsetContainer()

    // public static function getContainer()

    public static function hasContainer()
    {
        return false;
    }

    // public static function getApplication()

    // public static function runner()

    // public static function service($id)

    public static function hasService($id)
    {
        return false;
    }

    // public static function commandFactory()

    /**
     * Return the Drush logger object.
     *
     * @return LoggerInterface
     *
     * @deprecated Use injected logger if possible
     */
    public static function logger()
    {
        return drush_get_context('DRUSH_LOGGER');;
    }

    /**
     * Return the configuration object
     *
     * @return \Drush\DrushConfig
     *
     * @deprecated Use injected config if possible
     */
    public static function config()
    {
        if (!static::$config) {
            static::$config = new DrushConfig();
        }

        return static::$config;
    }

    /**
     * @return SiteAliasManager
     *
     * @deprecated Use injected alias manager if possible
     */
    public static function aliasManager()
    {
        if (!static::$aliasManager) {
            static::$aliasManager = new AliasManagerAdapter();
        }

        return static::$aliasManager;
    }

    /**
     * @return ProcessManager
     *
     * @deprecated Use injected process manager instead. @see Drush::drush()
     */
    public static function processManager()
    {
        if (!static::$processManager) {
            static::$processManager = new ProcessManager();
            ProcessManager::addTransports(static::$processManager);
            static::$processManager->setConfig(new ConfigAdapter(new DrushConfig()));
            // TODO: static::$processManager->setConfigRuntime()
        }

        return static::$processManager;
    }

    /**
     * Return the input object
     *
     * @return InputInterface
     */
    public static function input()
    {
        if (!static::$input) {
            static::$input = annotationcommand_adapter_input();
        }

        return static::$input;
    }

    /**
     * Return the output object
     *
     * @return OutputInterface
     */
    public static function output()
    {
        if (!static::$output) {
            static::$output = new DrushOutputAdapter();
        }

        return static::$output;
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
        return static::processManager()->drush($siteAlias, $command, $args, $options, $options_double_dash);
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
        return static::processManager()->siteProcess($siteAlias, $args, $options, $options_double_dash);
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
        return static::processManager()->process($commandline, $cwd, $env, $input, $timeout);
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
        return static::processManager()->shell($command, $cwd, $env, $input, $timeout);
    }

    /**
     * Return the path to this Drush.
     *
     * @deprecated Inject configuration and use $this->getConfig()->drushScript().
     */
    public static function drushScript()
    {
        return \Drush\Drush::config()->drushScript();
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
     * Return 'true' if we are in backend mode
     *
     * @deprecated Inject configuration and use $this->getConfig()->backend().
     */
    public static function backend()
    {
        return \Drush\Drush::config()->backend();
    }

    /**
     * Return 'true' if we are in affirmative mode
     */
    public static function affirmative()
    {
        return drush_get_context('DRUSH_AFFIRMATIVE');
    }

    /**
     * Return 'true' if we are in negative mode
     */
    public static function negative()
    {
        return drush_get_context('DRUSH_NEGATIVE');
    }

    /**
     * Return 'true' if we are in verbose mode
     */
    public static function verbose()
    {
        return drush_get_context('DRUSH_VERBOSE');
    }

    /**
     * Return 'true' if we are in debug mode
     */
    public static function debug()
    {
        return drush_get_context('DRUSH_DEBUG');
    }

    // public static function bootstrapManager()

    // public static function bootstrap()

    public static function redispatchOptions()
    {
        return drush_redispatch_get_options();
    }
}
