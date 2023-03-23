<?php

declare(strict_types=1);

namespace Drush\Config;

use Consolidation\Config\ConfigInterface;
use Robo\Config\Config;
use Consolidation\Config\Loader\ConfigLoaderInterface;
use Drush\Config\Loader\YamlConfigLoader;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Util\EnvConfig;
use Symfony\Component\Filesystem\Path;

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
     * @var ConfigInterface
     */
    protected $config;

    protected $isLocal;

    protected $sources = false;

    protected $siteRoots = [];

    protected $composerRoot;

    protected $configFilePaths = [];

    protected $configFileVariant;

    protected $processedConfigPaths = [];

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
    public function __construct($envPrefix = '', $configFileVariant = '')
    {
        $this->configFileVariant = $configFileVariant;
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
     */
    public function setLocal(bool $isLocal): void
    {
        $this->isLocal = $isLocal;
    }

    /**
     * Keep track of the source that every config item originally came from.
     * Potentially useful in debugging.  If collectSources(true) is called,
     * then the sources will be accumulated as config files are loaded. Otherwise,
     * this information will not be saved.
     *
     * @return $this
     */
    public function collectSources(bool $collect = true): self
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
    public function configFilePaths(): array
    {
        return $this->configFilePaths;
    }

    /**
     * Accumulate the sources provided by the configuration loader.
     */
    protected function addToSources(array $sources): void
    {
        if (!is_array($this->sources)) {
            return;
        }
        $this->sources = array_merge_recursive($this->sources, $sources);
    }

    /**
     * Return the configuration object. Create it and load it with
     * all identified configuration if necessary.
     */
    public function config(): ConfigInterface
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
     */
    public function addEnvironment(Environment $environment): self
    {
        $this->config->getContext(self::ENVIRONMENT_CONTEXT)->import($environment->exportConfigData());
        return $this;
    }

    /**
     *  Add config paths defined in preflight configuration.
     *
     * @param array $paths
     */
    public function addPreflightConfigFiles($filepaths): self
    {
        $this->addConfigPaths(self::PREFLIGHT_CONTEXT, (array) $filepaths);
        return $this;
    }

    /**
     * Take any configuration from the active alias record, and add it
     * to our configuration.
     */
    public function addAliasConfig($aliasConfig): self
    {
        $this->config->addContext(self::ALIAS_CONTEXT, $aliasConfig);
        return $this;
    }


    /**
     * Given the path provided via --config and the user's home directory,
     * add all of the user configuration paths.
     *
     * In 'local' mode, only the --config location is used.
     */
    public function addUserConfig($configPaths, $systemConfigPath, $userConfigDir): self
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
     */
    public function addDrushConfig(string $drushProjectDir): self
    {
        if (!$this->isLocal) {
            $this->addConfigPaths(self::DRUSH_CONTEXT, [$drushProjectDir]);
        }
        return $this;
    }

    /**
     * Add any configuration files found around the Drupal root of the
     * selected site.
     *
     * @param Path to the selected Drupal site
     */
    public function addSitewideConfig($siteRoot): ?self
    {
        // There might not be a site.
        if (!is_dir($siteRoot)) {
            return null;
        }

        // We might have already processed this root.
        $siteRoot = realpath($siteRoot);
        if (in_array($siteRoot, $this->siteRoots)) {
            return null;
        }

        // Remember that we've seen this location.
        $this->siteRoots[] = $siteRoot;

        $this->addConfigPaths(self::DRUPAL_CONTEXT, [ dirname($siteRoot) . '/drush', "$siteRoot/drush", "$siteRoot/sites/all/drush" ]);
        return $this;
    }

    /**
     * Add any configuration file found at any of the provided paths. Both the
     * provided location, and the directory `config` inside each provided location
     * is searched for a drush.yml file.
     *
     * @param string $contextName Which context to put all configuration files in.
     * @param string[] $paths List of paths to search for configuration.
     */
    public function addConfigPaths(string $contextName, array $paths): self
    {
        $loader = new YamlConfigLoader();
        // Make all of the config values parsed so far available in evaluations.
        $reference = $this->config()->export();
        $processor = new ConfigProcessor();
        $processor->useMergeStrategyForKeys(['drush.paths.include', 'drush.paths.alias-path']);
        $context = $this->config->getContext($contextName);
        $processor->add($context->export());

        $candidates = [
            'drush.yml',
        ];
        if ($this->configFileVariant) {
            $candidates[] = "drush{$this->configFileVariant}.yml";
        }
        $candidates = $this->expandCandidates($candidates, 'config/');
        $config_files = $this->findConfigFiles($paths, $candidates);
        $this->addConfigFiles($processor, $loader, $config_files);

        // Complete config import.
        $this->addToSources($processor->sources());
        $context->import($processor->export($reference));
        $this->config->addContext($contextName, $context);
        $this->processedConfigPaths = array_merge($this->processedConfigPaths, $paths);

        // Recursive case.
        if ($context->has('drush.paths.config')) {
            $new_config_paths = array_diff((array) $context->get('drush.paths.config'), $this->processedConfigPaths);
            if ($new_config_paths) {
                $this->addConfigPaths($contextName, $new_config_paths);
            }
        }

        return $this;
    }

    /**
     * Adds $configFiles to the list of config files.
     */
    protected function addConfigFiles(ConfigProcessor $processor, ConfigLoaderInterface $loader, array $configFiles): void
    {
        foreach ($configFiles as $configFile) {
            $processor->extend($loader->load($configFile));
            $this->configFilePaths[] = Path::canonicalize($configFile);
        }
    }

    /**
     * Given a list of paths, and candidates that might exist at each path,
     * return all of the candidates that can be found. Candidates may be
     * either directories or files.
     *
     * @return string[] paths
     */
    protected function identifyCandidates(array $paths, array $candidates): array
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
     * @param string[] $candidates
     * @return string[]
     */
    protected function identifyCandidatesAtPath(string $path, array $candidates): array
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
     */
    public function getSiteAliasPaths(array $paths, Environment $environment): array
    {
        // In addition to the paths passed in to us (from --alias-path
        // commandline options), add some site-local locations.
        $siteroot_parents = array_map(
            function ($dir) {
                return dirname($dir);
            },
            $this->siteRoots
        );
        $base_dirs = array_filter(array_merge($this->siteRoots, $siteroot_parents, [$this->composerRoot]));
        $site_local_paths = array_map(
            function ($item) {
                return Path::join($item, '/drush/sites');
            },
            array_unique($base_dirs)
        );

        return array_merge($paths, $site_local_paths);
    }

    /**
     * Get the commandfile paths according to preflight arguments.
     *
     * @param $commandPaths
     * @param $root
     */
    public function getCommandFilePaths(array $commandPaths, string $root): array
    {
        $builtin = $this->getBuiltinCommandFilePaths();
        $included = $this->getIncludedCommandFilePaths($commandPaths);
        $site = $this->getSiteCommandFilePaths($root);

        return array_merge(
            $builtin,
            $included,
            $site
        );
    }

    /**
     * Return all of the built-in commandfile locations
     */
    protected function getBuiltinCommandFilePaths(): array
    {
        return [
            dirname(__DIR__),
        ];
    }

    /**
     * Return all of the commandfile locations specified via
     * an 'include' option.
     */
    protected function getIncludedCommandFilePaths($commandPaths): array
    {
        $searchpath = [];

        // Commands specified by 'include' option
        foreach ($commandPaths as $key => $commandPath) {
            // Check to see if there is a `#` in the include path.
            // This indicates an include path that has a namespace,
            // e.g. `namespace#/path`.
            if (is_numeric($key) && str_contains($commandPath, '#')) {
                [$key, $commandPath] = explode('#', $commandPath, 2);
            }
            $sep = ($this->config->isWindows()) ? ';' : ':';
            foreach (explode($sep, $commandPath) as $path) {
                if (is_dir($path)) {
                    if (is_numeric($key)) {
                        $searchpath[] = $path;
                    } else {
                        $key = strtr($key, '-/', '_\\');
                        $searchpath[$key] = $path;
                    }
                }
            }
        }

        return $searchpath;
    }

    /**
     * Return all of the commandfile paths in any '$root/drush' or
     * 'dirname($root)/drush' directory that contains a composer.json
     * file or a 'Commands' or 'src/Commands' directory.
     */
    protected function getSiteCommandFilePaths($root): array
    {
        $directories = ["$root/drush", dirname($root) . '/drush', "$root/sites/all/drush"];

        return array_filter($directories, 'is_dir');
    }

    /**
     * Sets the composer root.
     */
    public function setComposerRoot($selectedComposerRoot): void
    {
        $this->composerRoot = $selectedComposerRoot;

        // Also export the project directory: the composer root of the
        // project that contains the selected site.
        $this->config->getContext(self::ENVIRONMENT_CONTEXT)->set('runtime.project', $this->composerRoot);
    }

    /**
     * Double the candidates, adding '$prefix' before each existing one.
     */
    public function expandCandidates($candidates, $prefix): array
    {
        $additional = array_map(
            function ($item) use ($prefix) {
                return $prefix . $item;
            },
            $candidates
        );
        return array_merge($candidates, $additional);
    }

    /**
     * Given an array of paths, separates files and directories.
     *
     * @param array $paths
     *   An array of config paths. These may be config files or paths to dirs
     *   containing config files.
     * @param array $candidates
     *   An array filenames that are considered config files.
     *
     * @return
     *   An array whose first item is an array of files, and the second item is an
     *   array of dirs.
     */
    protected function findConfigFiles(array $paths, array $candidates): array
    {
        $files = [];
        $dirs = [];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                if (is_dir($path)) {
                    $dirs[] = realpath($path);
                } else {
                    $files[] = realpath($path);
                }
            }
        }

        // Search directories for config file candidates.
        $discovered_config_files = $this->identifyCandidates($dirs, $candidates);

        // Merge discovered candidates with explicitly specified config files.
        $config_files = array_merge($discovered_config_files, $files);

        return $config_files;
    }

    /**
     * Attempt to load site specific configuration.
     *
     * @param DrushConfig $config
     *   The config object.
     * @param $siteConfig
     *   The site-specific config file.
     *
     * @return
     *   Whether the config exists and was processed.
     */
    public static function addSiteSpecificConfig(DrushConfig $config, $siteConfig): bool
    {
        if (file_exists($siteConfig)) {
            $loader = new YamlConfigLoader();
            $processor = new ConfigProcessor();
            $reference = $config->export();
            $context = $config->getContext(ConfigLocator::SITE_CONTEXT);
            $processor->add($context->export());
            $processor->extend($loader->load($siteConfig));
            $context->import($processor->export($reference));
            $config->addContext(ConfigLocator::SITE_CONTEXT, $context);
            $presetConfig = $config->get('runtime.config.paths');
            $config->set('runtime.config.paths', array_merge($presetConfig, [$siteConfig]));
            return true;
        } else {
            return false;
        }
    }
}
