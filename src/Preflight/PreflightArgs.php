<?php
namespace Drush\Preflight;

use Consolidation\Config\Config;
use Consolidation\Config\ConfigInterface;

/**
 * Storage for arguments preprocessed during preflight.
 *
 * Holds @sitealias, if present, and a limited number of global options.
 */
class PreflightArgs extends Config implements PreflightArgsInterface
{
    /**
     * @var $args Remaining arguments not handled by the preprocessor
     */
    protected $args;

    const DRUSH_CONFIG_CONTEXT_NAMESPACE = 'context';
    const ALIAS = 'alias';
    const ALIAS_PATH = 'alias-path';
    const COMMAND_PATH = 'include';
    const CONFIG_PATH = 'config';
    const COVERAGE_FILE = 'coverage-file';
    const LOCAL = 'local';
    const ROOT = 'root';
    const URI = 'uri';
    const SIMULATE = 'simulate';
    const BACKEND = 'backend';

    public function __construct(array $data = null)
    {
        parent::__construct($data);
    }

    /**
     * @inheritdoc
     */
    public function optionsWithValues()
    {
        return [
            '-r=' => 'setSelectedSite',
            '--root=' => 'setSelectedSite',
            '-l=' => 'setUri',
            '--uri=' => 'setUri',
            '-c=' => 'setConfigPath',
            '--config=' => 'setConfigPath',
            '--alias-path=' => 'setAliasPath',
            '--include=' => 'setCommandPath',
            '--local' => 'setLocal',
            '--simulate' => 'setSimulate',
            '--backend' => 'setBackend',
            '--drush-coverage=' => 'setCoverageFile',
        ];
    }

    /**
     * Map of option key to the corresponding config key to store the
     * preflight option in.
     */
    protected function optionConfigMap()
    {
        return [
            self::SIMULATE =>       \Robo\Config\Config::SIMULATE,
            self::BACKEND =>        self::BACKEND,
            self::ALIAS_PATH =>     self::DRUSH_CONFIG_CONTEXT_NAMESPACE . '.' . self::ALIAS_PATH,
            self::CONFIG_PATH =>    self::DRUSH_CONFIG_CONTEXT_NAMESPACE . '.' . self::CONFIG_PATH,
            self::COMMAND_PATH =>   self::DRUSH_CONFIG_CONTEXT_NAMESPACE . '.' . self::COMMAND_PATH,
            self::LOCAL =>          self::DRUSH_CONFIG_CONTEXT_NAMESPACE . '.' . self::LOCAL,
        ];
    }

    /**
     * @inheritdoc
     */
    public function applyToConfig(ConfigInterface $config)
    {
        // Copy the relevant preflight options to the applicable configuration namespace
        foreach ($this->optionConfigMap() as $option_key => $config_key) {
            $config->set($config_key, $this->get($option_key));
        }
    }

    /**
     * @inheritdoc
     */
    public function args()
    {
        return $this->args;
    }

    public function applicationPath()
    {
        return reset($this->args);
    }

    /**
     * @inheritdoc
     */
    public function addArg($arg)
    {
        $this->args[] = $arg;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function passArgs($args)
    {
        $this->args = array_merge($this->args, $args);
        return $this;
    }

    public function alias()
    {
        return $this->get(self::ALIAS);
    }

    public function hasAlias()
    {
        return $this->has(self::ALIAS);
    }

    public function setAlias($alias)
    {
        return $this->set(self::ALIAS, $alias);
    }

    /**
     * Get the selected site. Here, the default will typically be the cwd.
     */
    public function selectedSite($default = false)
    {
        return $this->get(self::ROOT, $default);
    }

    public function setSelectedSite($root)
    {
        return $this->set(self::ROOT, $root);
    }

    public function uri($default = false)
    {
        return $this->get(self::URI, $default);
    }

    public function setUri($uri)
    {
        return $this->set(self::URI, $uri);
    }

    public function configPath()
    {
        return $this->get(self::CONFIG_PATH);
    }

    public function setConfigPath($configPath)
    {
        return $this->set(self::CONFIG_PATH, $configPath);
    }

    public function aliasPath()
    {
        return $this->get(self::ALIAS_PATH);
    }

    public function setAliasPath($aliasPath)
    {
        return $this->set(self::ALIAS_PATH, $aliasPath);
    }

    public function commandPath()
    {
        return $this->get(self::COMMAND_PATH);
    }

    public function setCommandPath($commandPath)
    {
        return $this->set(self::COMMAND_PATH, $commandPath);
    }

    public function isLocal()
    {
        return $this->get(self::LOCAL);
    }

    public function setLocal($isLocal)
    {
        return $this->set(self::LOCAL, $isLocal);
    }

    public function isSimulated()
    {
        return $this->get(self::SIMULATE);
    }

    public function setSimulate($simulate)
    {
        return $this->set(self::SIMULATE, $simulate);
    }

    public function isBackend()
    {
        return $this->get(self::BACKEND);
    }

    public function setBackend($backend)
    {
        return $this->set(self::BACKEND, $backend);
    }

    public function coverageFile()
    {
        return $this->get(self::COVERAGE_FILE);
    }

    public function setCoverageFile($coverageFile)
    {
        return $this->set(self::COVERAGE_FILE, $coverageFile);
    }
}
