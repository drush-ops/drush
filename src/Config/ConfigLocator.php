<?php
namespace Drush\Config;

use Consolidation\Config\Loader\ConfigLoaderInterface;
use Consolidation\Config\Loader\YamlConfigLoader;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Util\ConfigOverlay;
use Drush\Preflight\PreflightArgsInterface;

/**
 * Locate Drush configuration files and load them into the configuration
 * instance.
 *
 * This class knows how to find all of the global and site-local
 * configuration files for Drush, as long as it is provided with
 * the necessary base directories:
 *
 * - The user's home directory
 * - The values provided for --config and --alias-path
 * - The Drupal root
 *
 * There are two operating modes that are supported:
 *
 * - Normal: All config locations are used.
 * - Local:  The global locations are omitted.
 *
 * The mode is set via the `setLocal()` method.
 */
class ConfigLocator
{
    /**
     * @var \Robo\Config
     */
    protected $config;

    protected $isLocal;

    protected $sources = false;

    protected $siteRoots = [];

    protected $composerRoot;

    protected $configFilePaths = [];

    /*
     * From context.inc:
     *
     *   Specified by the script itself :
     *     process  : Generated in the current process.
     *     cli      : Passed as --option=value to the command line.
     *     stdin    : Passed as a JSON encoded string through stdin.
     *     specific : Defined in a command-specific option record, and
     *                set in the command context whenever that command is used.
     *     alias    : Defined in an alias record, and set in the
     *                alias context whenever that alias is used.
     *
     *   Specified by config files :
     *     custom   : Loaded from the config file specified by --config or -c
     *     site     : Loaded from the drushrc.php file in the Drupal site directory.
     *     drupal   : Loaded from the drushrc.php file in the Drupal root directory.
     *     user     : Loaded from the drushrc.php file in the user's home directory.
     *     home.drush Loaded from the drushrc.php file in the $HOME/.drush directory.
     *     system   : Loaded from the drushrc.php file in the system's $PREFIX/etc/drush directory.
     *     drush    : Loaded from the drushrc.php file in the same directory as drush.php.
     *
     *   Specified by the script, but has the lowest priority :
     *     default  : The script might provide some sensible defaults during init.
     */

    // 'process' context is provided by ConfigOverlay
    const ENVIRONMENT_CONTEXT = 'environment'; // new context
    const PREFLIGHT_CONTEXT = 'cli';
    // 'stdin' context not implemented
    // 'specific' context obsolete; command-specific options handled differently by annotated command library
    const ALIAS_CONTEXT = 'alias';
    // custom context is obsolect (loaded in USER_CONTEXT)
    const SITE_CONTEXT = 'site';
    const DRUPAL_CONTEXT = 'drupal';
    const USER_CONTEXT = 'user';
    // home.drush is obsolete (loaded in USER_CONTEXT)
    // system context is obsolect (loaded in USER_CONTEXT - note priority change)
    const DRUSH_CONTEXT = 'drush';

    // 'default' context is provided by ConfigOverlay

    /**
     * ConfigLocator constructor
     */
    public function __construct()
    {
        $this->config = new ConfigOverlay();

        // Add placeholders to establish priority. We add
        // contexts from lowest to highest priority.
        $this->config->addPlaceholder(self::DRUSH_CONTEXT);
        $this->config->addPlaceholder(self::USER_CONTEXT);
        $this->config->addPlaceholder(self::DRUPAL_CONTEXT);
        $this->config->addPlaceholder(self::SITE_CONTEXT); // not implemented yet (multisite)
        $this->config->addPlaceholder(self::ALIAS_CONTEXT);
        $this->config->addPlaceholder(self::PREFLIGHT_CONTEXT);
        $this->config->addPlaceholder(self::ENVIRONMENT_CONTEXT);

        $this->isLocal = false;

        $this->configFilePaths = [];
    }

    /**
     * Put the config locator into 'local 'mode.
     *
     * @param bool $isLocal
     */
    public function setLocal($isLocal)
    {
        $this->isLocal = $isLocal;
    }

    /**
     * Keep track of the source that every config item originally came from.
     * Potentially useful in debugging.  If collectSources(true) is called,
     * then the sources will be accumulated as config files are loaded. Otherwise,
     * this information will not be saved.
     *
     * @param bool $collect
     * @return $this
     */
    public function collectSources($collect = true)
    {
        $this->sources = $collect ? [] : false;
        return $this;
    }

    /**
     * Return all of the sources for every configuration item. The key
     * is the address of the configuration item, and the value is the
     * configuration file it was loaded from. Note that this method will
     * return just an empty array unless collectSources(true) is called
     * prior to loading configuration files.
     *
     * @return array
     */
    public function sources()
    {
        return $this->sources;
    }

    /**
     * Return a list of all configuration files that were loaded.
     *
     * @return string[]
     */
    public function configFilePaths()
    {
        return $this->configFilePaths;
    }

    /**
     * Accumulate the sources provided by the configuration loader.
     */
    protected function addToSources(array $sources)
    {
        if (!is_array($this->sources)) {
            return;
        }
        $this->sources = array_merge_recursive($this->sources, $sources);
    }

    /**
     * Return the configuration object. Create it and load it with
     * all identified configuration if necessary.
     *
     * @return Config
     */
    public function config()
    {
        return $this->config;
    }

    /**
     * Exports all of the information stored in the environment, and adds
     * it to the configuration.  The Environment object itself is only
     * available during preflight; the information exported here may be
     * obtained by commands et. al. as needed. @see Environment::exportConfigData()
     *
     * @param Environment $environent
     * @return $this
     */
    public function addEnvironment(Environment $environment)
    {
        $this->config->getContext(self::ENVIRONMENT_CONTEXT)->import($environment->exportConfigData());
        return $this;
    }

    /**
     * Unused. See PreflightArgs::applyToConfig() instead.
     *
     * @param array $preflightConfig
     * @return $this
     */
    public function addPreflightConfig($preflightConfig)
    {
        $this->config->addContext(self::PREFLIGHT_CONTEXT, $preflightConfig);
        return $this;
    }

    /**
     * Take any configuration from the active alias record, and add it
     * to our configuratino.
     * @return $this
     */
    public function addAliasConfig($aliasConfig)
    {
        $this->config->addContext(self::ALIAS_CONTEXT, $aliasConfig);
        return $this;
    }


    /**
     * Given the path provided via --config and the user's home directory,
     * add all of the user configuration paths.
     *
     * In 'local' mode, only the --config location is used.
     * @return $this
     */
    public function addUserConfig($configPaths, $systemConfigPath, $userConfigDir)
    {
        $paths = $configPaths;
        if (!$this->isLocal) {
            $paths = array_merge($paths, [ $systemConfigPath, $userConfigDir ]);
        }
        $this->addConfigPaths(self::USER_CONTEXT, $paths);
        return $this;
    }

    /**
     * Add the Drush project directory as a configuration search location.
     *
     * @param $drushProjectDir path to the drush project directory
     * @return $this
     */
    public function addDrushConfig($drushProjectDir)
    {
        if (!$this->isLocal) {
            $this->addConfigPaths(self::DRUSH_CONTEXT, [ $drushProjectDir ]);
        }
        return $this;
    }

    /**
     * Add any configuration files found around the Drupal root of the
     * selected site.
     *
     * @param Path to the selected Drupal site
     * @return $this
     */
    public function addSitewideConfig($siteRoot)
    {
        // There might not be a site.
        if (!is_dir($siteRoot)) {
            return;
        }

        // We might have already processed this root.
        $siteRoot = realpath($siteRoot);
        if (in_array($siteRoot, $this->siteRoots)) {
            return;
        }

        // Remember that we've seen this location.
        $this->siteRoots[] = $siteRoot;

        $this->addConfigPaths(self::DRUPAL_CONTEXT, [ dirname($siteRoot) . '/drush', "$siteRoot/drush", "$siteRoot/sites/all/drush" ]);
        return $this;
    }

    /**
     * Add any configruation file found at any of the provided paths. Both the
     * provided location, and the directory `config` inside each provided location
     * is searched for a drush.yml file.
     *
     * @param string $contextName Which context to put all configuration files in.
     * @param string[] $paths List of paths to search for configuration.
     * @return $this
     */
    public function addConfigPaths($contextName, $paths)
    {
        $loader = new YamlConfigLoader();
        $candidates = [
            'drush.yml',
            'config/drush.yml',
        ];

        $processor = new ConfigProcessor();
        $context = $this->config->getContext($contextName);
        $processor->add($context->export());
        $this->addConfigCandidates($processor, $loader, $paths, $candidates);
        $this->addToSources($processor->sources());
        $context->import($processor->export());
        $this->config->addContext($contextName, $context);

        return $this;
    }

    /**
     * Worker function for addConfigPaths
     *
     * @param ConfigProcessor $processor
     * @param ConfigLoaderInterface $loader
     * @param string[] $paths
     * @param string[] $candidates
     */
    protected function addConfigCandidates(ConfigProcessor $processor, ConfigLoaderInterface $loader, $paths, $candidates)
    {
        $configFiles = $this->locateConfigs($paths, $candidates);
        foreach ($configFiles as $configFile) {
            $processor->extend($loader->load($configFile));
            $this->configFilePaths[] = $configFile;
        }
    }

    /**
     * Find available configuration files.
     *
     * @param string[] $paths
     * @param string[] $candidates
     * @return string[] paths
     */
    protected function locateConfigs($paths, $candidates)
    {
        $configFiles = [];
        foreach ($paths as $path) {
            $configFiles = array_merge($configFiles, $this->locateConfig($path, $candidates));
        }
        return $configFiles;
    }

    /**
     * Search for all config candidate locations at a single path.
     *
     * @param string $path
     * @param string[] $candidates
     * @return string[]
     */
    protected function locateConfig($path, $candidates)
    {
        if (!is_dir($path)) {
            return [];
        }

        $result = [];
        foreach ($candidates as $candidate) {
            $configFile = "$path/$candidate";
            if (file_exists($configFile)) {
                $result[] = $configFile;
            }
        }
        return $result;
    }

    /**
     * Get the site aliases according to preflight arguments and environment.
     *
     * @param $preflightArgs
     * @param Environment $environment
     *
     * @return array
     */
    public function getSiteAliasPaths(PreflightArgsInterface $preflightArgs, Environment $environment)
    {
        $paths = $preflightArgs->aliasPaths();
        foreach ($this->siteRoots as $siteRoot) {
            $paths[] = $siteRoot . '/drush';
        }
        $paths[] = $this->composerRoot . '/drush';

        return $paths;
    }

    /**
     * Get the commandfile paths according to preflight arguments.
     *
     * @param $preflightArgs
     *
     * @return array
     */
    public function getCommandFilePaths(PreflightArgsInterface $preflightArgs)
    {
        // Start with the built-in commands.
        $searchpath = [
            dirname(__DIR__),
        ];

        // Commands specified by 'include' option
        $commandPaths = $preflightArgs->commandPaths();
        foreach ($commandPaths as $commandPath) {
            if (is_dir($commandPath)) {
                $searchpath[] = $commandPath;
            }
        }

        return $searchpath;
    }

    /**
     * Sets the composer root.
     *
     * @param $selectedComposerRoot
     */
    public function setComposerRoot($selectedComposerRoot)
    {
        $this->composerRoot = $selectedComposerRoot;
    }
}
