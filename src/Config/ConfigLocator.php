<?php
namespace Drush\Config;

use Consolidation\Config\Loader\ConfigLoaderInterface;
use Drush\Config\Loader\YamlConfigLoader;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Util\EnvConfig;
use Symfony\Component\Finder\Finder;

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

    protected $drupalRoots = [];

    protected $siteUris = [];

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
     *     site     : Loaded from the drush.yml file in the Drupal site directory.
     *     drupal   : Loaded from the drush.yml file in the Drupal root directory.
     *     user     : Loaded from the drush.yml file in the user's home directory.
     *     home.drush Loaded from the drush.yml file in the $HOME/.drush directory.
     *     system   : Loaded from the drush.yml file in the system's $PREFIX/etc/drush directory.
     *     drush    : Loaded from the drush.yml file in the same directory as drush.php.
     *
     *   Specified by the script, but has the lowest priority :
     *     default  : The script might provide some sensible defaults during init.
     */

    // 'process' context is provided by ConfigOverlay
    const ENVIRONMENT_CONTEXT = 'environment'; // This is more of a 'runtime' context
    const PREFLIGHT_CONTEXT = 'cli';
    // 'stdin' context not implemented
    // 'specific' context obsolete; command-specific options handled differently by annotated command library
    const ALIAS_CONTEXT = 'alias';
    // custom context is obsolete (loaded in USER_CONTEXT)
    const SITE_CONTEXT = 'site';
    const DRUPAL_CONTEXT = 'drupal';
    const USER_CONTEXT = 'user';
    // home.drush is obsolete (loaded in USER_CONTEXT)
    // system context is obsolete (loaded in USER_CONTEXT - note priority change)
    const ENV_CONTEXT = 'env';
    const DRUSH_CONTEXT = 'drush';

    // 'default' context is provided by ConfigOverlay

    /**
     * ConfigLocator constructor
     */
    public function __construct($envPrefix = '')
    {
        $this->config = new DrushConfig();

        // Add placeholders to establish priority. We add
        // contexts from lowest to highest priority.
        $this->config->addPlaceholder(self::DRUSH_CONTEXT);
        if (!empty($envPrefix)) {
            $envConfig = new EnvConfig($envPrefix);
            $this->config->addContext(self::ENV_CONTEXT, $envConfig);
        }
        $this->config->addPlaceholder(self::USER_CONTEXT);
        $this->config->addPlaceholder(self::DRUPAL_CONTEXT);
        $this->config->addPlaceholder(self::SITE_CONTEXT);
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
     * @param $drupalRoot
     *   Path to the selected Drupal site.
     * @return $this
     */
    public function addDrupalConfig($drupalRoot)
    {
        // There might not be a site.
        if (!is_dir($drupalRoot)) {
            return;
        }

        // We might have already processed this root.
        $drupalRoot = realpath($drupalRoot);
        if (in_array($drupalRoot, $this->drupalRoots)) {
            return;
        }

        // Remember that we've seen this location.
        $this->drupalRoots[] = $drupalRoot;

        $this->addConfigPaths(self::DRUPAL_CONTEXT, [ dirname($drupalRoot) . '/drush', "$drupalRoot/drush", "$drupalRoot/sites/all/drush" ]);
        return $this;
    }

    /**
     * Add any configuration files found around the multisite directory.
     *
     * @param string $drupalRoot
     *   Path to the selected Drupal site.
     *
     * @param \Drush\SiteAlias\AliasRecord $alias
     *   Site URI of the multisite.
     *
     * @return $this
     */
    public function addSiteConfig($drupalRoot, $alias)
    {
        $uri = $alias->uri() ?: 'default';

        // Convert a fqdn to a hostname and look for matching entry in
        // sites/sites.php.
        if (filter_var($uri, FILTER_VALIDATE_URL)) {
            $hostname = $this->convertUriToHostname($uri);

            // If $hostname matches a sites.php mappings, use dir from mapping.
            if ($dir_name = $this->lookupSiteDirFromHostname($hostname, $drupalRoot)) {
                $uri = $dir_name;
            }
        }

        // There might not be a site directory.
        $site_dir = "$drupalRoot/sites/$uri";
        if (!is_dir($site_dir)) {
            return;
        }

        // We might have already processed this site.
        if (in_array($uri, $this->siteUris)) {
            return;
        }

        // Remember that we've seen this site.
        $this->siteUris[] = $uri;

        $this->addConfigPaths(self::SITE_CONTEXT, [ "$site_dir", "$site_dir/drush" ]);
        return $this;
    }

    /**
     * Convert from a URI to a site directory.
     *
     * @param string $uri
     *   A uri, such as http://domain.com:8080/drupal
     *
     * @return string
     *   The hostname.
     */
     public function convertUriToHostname($uri) {
        $uri = str_replace('http://', '', $uri);
        $uri = str_replace('https://', '', $uri);
        if (drush_is_windows()) {
            // Handle absolute paths on windows
            $uri = str_replace(array(':/', ':\\'), array('.', '.'), $uri);
        }
        $hostname = str_replace(array('/', ':', '\\'), array('.', '.', '.'), $uri);

        return $hostname;
    }

    /**
     * Lookup a site's directory via the sites.php file given a hostname.
     *
     * @param $hostname
     *   The hostname of a site. May be converted from URI.
     *
     * @return $drupalRoot
     *   The directory associated with that hostname.
     */
    public function lookupSiteDirFromHostname($hostname, $drupalRoot) {
        if (file_exists($drupalRoot . '/sites/sites.php')) {
            $sites = array();
            // This will overwrite $sites with the desired mappings.
            include ($drupalRoot . '/sites/sites.php');
            return isset($sites[$hostname]) ? $sites[$hostname] : FALSE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * Add any configuration file found at any of the provided paths. Both the
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

        // Make all of the config values parsed so far available in evaluations
        $reference = $this->config()->export();

        $processor = new ConfigProcessor();
        $context = $this->config->getContext($contextName);
        $processor->add($context->export());
        $this->addConfigCandidates($processor, $loader, $paths, $candidates);
        $this->addToSources($processor->sources());
        $context->import($processor->export($reference));
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
        $configFiles = $this->identifyCandidates($paths, $candidates);
        foreach ($configFiles as $configFile) {
            $processor->extend($loader->load($configFile));
            $this->configFilePaths[] = $configFile;
        }
    }

    /**
     * Given a list of paths, and candidates that might exist at each path,
     * return all of the candidates that can be found. Candidates may be
     * either directories or files.
     *
     * @param string[] $paths
     * @param string[] $candidates
     * @return string[] paths
     */
    protected function identifyCandidates($paths, $candidates)
    {
        $configFiles = [];
        foreach ($paths as $path) {
            $configFiles = array_merge($configFiles, $this->identifyCandidatesAtPath($path, $candidates));
        }
        return $configFiles;
    }

    /**
     * Search for all matching candidate locations at a single path.
     * Candidate locations may be either directories or files.
     *
     * @param string $path
     * @param string[] $candidates
     * @return string[]
     */
    protected function identifyCandidatesAtPath($path, $candidates)
    {
        if (!is_dir($path)) {
            return [];
        }

        $result = [];
        foreach ($candidates as $candidate) {
            $configFile = empty($candidate) ? $path : "$path/$candidate";
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
    public function getSiteAliasPaths($paths, Environment $environment)
    {
        // In addition to the paths passed in to us (from --alias-paths
        // commandline options), add some site-local locations.
        $base_dirs = array_filter(array_merge($this->drupalRoots, [$this->composerRoot]));
        $site_local_paths = array_map(
            function ($item) {
                return "$item/drush/sites";
            },
            $base_dirs
        );
        $paths = array_merge($paths, $site_local_paths);

        return $paths;
    }

    /**
     * Get the commandfile paths according to preflight arguments.
     *
     * @param $preflightArgs
     *
     * @return array
     */
    public function getCommandFilePaths($commandPaths, $root)
    {
        $builtin = $this->getBuiltinCommandFilePaths();
        $included = $this->getIncludedCommandFilePaths($commandPaths);
        $site = $this->getSiteCommandFilePaths(["$root/drush", dirname($root) . '/drush']);

        return array_merge(
            $builtin,
            $included,
            $site
        );
    }

    /**
     * Return all of the built-in commandfile locations
     */
    protected function getBuiltinCommandFilePaths()
    {
        return [
            dirname(__DIR__),
        ];
    }

    /**
     * Return all of the commandfile locations specified via
     * an 'include' option.
     */
    protected function getIncludedCommandFilePaths($commandPaths)
    {
        $searchpath = [];
        // Commands specified by 'include' option
        foreach ($commandPaths as $commandPath) {
            if (is_dir($commandPath)) {
                $searchpath[] = $commandPath;
            }
        }
        return $searchpath;
    }

    /**
     * Return all of the commandfile paths in any '$root/drush' or
     * 'dirname($root)/drush' directory that contains a composer.json
     * file or a 'Commands' or 'src/Commands' directory.
     */
    protected function getSiteCommandFilePaths($directories)
    {
        $result = [];

        $directories = array_filter($directories, 'is_dir');

        if (empty($directories)) {
            return $result;
        }

        // Find projects
        $finder = new Finder();
        $finder->files()
            ->ignoreUnreadableDirs()
            ->path('#composer.json$|^src/Commands|^Commands#')
            ->in($directories)
            ->depth('<= 3');

        foreach ($finder as $file) {
            $result[] = dirname($file->getRealPath());
        }

        return $result;
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
