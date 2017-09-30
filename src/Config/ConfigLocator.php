<?php
namespace Drush\Config;

use Consolidation\Config\Loader\ConfigLoaderInterface;
use Consolidation\Config\Loader\YamlConfigLoader;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Util\ConfigOverlay;

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
     */
    public function setLocal($isLocal)
    {
        $this->isLocal = $isLocal;
    }

    // TODO: Not sure I need to manage the sources on a per-config-item basis
    // any longer. However, I still need to track the configuration files that
    // were loaded, so that these can be shown in `drush status`.
    public function collectSources($collect = true)
    {
        $this->sources = $collect ? [] : false;
    }

    public function sources()
    {
        return $this->sources;
    }

    public function configFilePaths()
    {
        return $this->configFilePaths;
    }

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
     */
    public function config()
    {
        return $this->config;
    }

    public function addEnvironment(Environment $environment)
    {
        $this->config->getContext(self::ENVIRONMENT_CONTEXT)->import($environment->exportConfigData());
    }

    public function addPreflightConfig($preflightConfig)
    {
        $this->config->addContext(self::PREFLIGHT_CONTEXT, $preflightConfig);
        return $this;
    }

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
     */
    public function addUserConfig($configPath, $systemConfigPath, $userConfigDir)
    {
        $paths = [ $configPath ];
        if (!$this->isLocal) {
            $paths = array_merge($paths, [ $systemConfigPath, $userConfigDir ]);
        }
        $this->addConfigPaths(self::USER_CONTEXT, $paths);
        return $this;
    }

    public function addDrushConfig($drushProjectDir)
    {
        if (!$this->isLocal) {
            $this->addConfigPaths(self::DRUSH_CONTEXT, [ $drushProjectDir ]);
        }
        return $this;
    }

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

    protected function addConfigCandidates(ConfigProcessor $processor, ConfigLoaderInterface $loader, $paths, $candidates)
    {
        $configFiles = $this->locateConfigs($paths, $candidates);
        foreach ($configFiles as $configFile) {
            $processor->extend($loader->load($configFile));
            $this->configFilePaths[] = $configFile;
        }
    }

    protected function locateConfigs($paths, $candidates)
    {
        $configFiles = [];
        foreach ($paths as $path) {
            $configFiles = array_merge($configFiles, $this->locateConfig($path, $candidates));
        }
        return $configFiles;
    }

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
}
