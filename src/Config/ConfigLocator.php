<?php
namespace Drush\Config;

use Consolidation\Config\Loader\YamlConfigLoader;
use Consolidation\Config\Loader\ConfigProcessor;

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
 * - Local:  The global locations are ommitted.
 *
 * The mode is set via the `setLocal()` method.
 */
class ConfigLocator
{
    /**
     * @var \Robo\Config
     */
    protected $config;

    protected $processor;

    protected $isLocal;

    public function __construct($config = null)
    {
        $this->config = $config;
        $this->isLocal = false;
    }

    /**
     * Put the config locator into 'local 'mode.
     */
    public function setLocal($isLocal)
    {
        $this->isLocal = $isLocal;
    }

    /**
     * Return the configuration processor.
     */
    protected function configProcessor()
    {
        // Create our processor if it does not already exist.
        if (!$this->processor) {
            $this->processor = new ConfigProcessor();
            // Seed the processor with the current configuration values,
            // if there is already a configuration object.
            if ($this->config) {
                $this->processor->add($this->config->export());
            }
        }
        return $this->processor;
    }

    /**
     * Create the configuration object
     */
    protected function createConfig()
    {
        // TODO: Is it going to cause problems to not use \Robo\Config()?
        return new \Consolidation\Config\Config();
        // return new \Robo\Config();
    }

    public function sources()
    {
        if ($this->processor) {
            return $this->processor->sources();
        }
        return [];
    }

    /**
     * Return the configuration object. Create it and load it with
     * all identified configuration if necessary.
     */
    public function config()
    {
        // Create our config object if we have not already done so.
        if (!isset($this->config)) {
            $this->config = $this->createConfig();
        }
        // If there are configuration values that are being processed,
        // then import them into the configuration.
        if ($this->processor) {
            $this->config->import($this->processor->export());
            $this->processor = null;
        }
        return $this->config;
    }

    /**
     * Given the path provided via --config and the user's home directory,
     * add all of the user configuration paths.
     *
     * In 'local' mode, only the --config location is used.
     */
    public function addUserConfig($configPath, $systemConfigPath, $home)
    {
        $paths = [ $configPath ];
        if (!$this->isLocal) {
            $paths = array_merge($paths, [ $systemConfigPath, $home, "$home/.drush" ]);
        }
        $this->addConfigPaths($paths);
    }

    public function addDrushConfig($drushProjectDir)
    {
        if (!$this->isLocal) {
            $this->addConfigPaths([ $drushProjectDir ]);
        }
    }

    public function addAliasConfig($alias, $aliasPath, $home)
    {
        // @TODO
    }

    public function addSiteConfig($siteRoot)
    {
        // There might not be a site
        if (!is_dir($siteRoot)) {
            return;
        }
        $this->addConfigPaths([ $siteRoot, "$siteRoot/drush" ]);
    }

    public function addConfigPaths($paths)
    {
        $configFiles = $this->locateConfigs($paths);
        if (empty($configFiles)) {
            return;
        }

        $loader = new YamlConfigLoader();
        foreach ($configFiles as $configFile) {
            $this->addLoader($loader->load($configFile));
        }
    }

    public function addLoader($loader)
    {
        $processor = $this->configProcessor();
        $processor->extend($loader);
        return $this;
    }

    protected function locateConfigs($paths)
    {
        $configFiles = [];
        foreach ($paths as $path) {
            $configFiles = array_merge($configFiles, $this->locateConfig($path));
        }
        return $configFiles;
    }

    protected function locateConfig($path)
    {
        if (!is_dir($path)) {
            return [];
        }

        $candidates = [
            'drush.yml',
            'config/drush.yml',
        ];

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
