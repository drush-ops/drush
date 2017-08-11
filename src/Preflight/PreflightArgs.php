<?php
namespace Drush\Preflight;

use Consolidation\Config\Config;

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

    const ALIAS = 'alias';
    const ALIAS_PATH = 'alias-path';
    const COMMAND_PATH = 'include';
    const CONFIG_PATH = 'config';
    const COVERAGE_FILE = 'coverage-file';
    const LOCAL = 'local';
    const ROOT = 'root';
    const URI = 'uri';

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
            '-c=' => 'setConfig',
            '--config=' => 'setConfigPath',
            '--alias-path=' => 'setAliasPath',
            '--include=' => 'setCommandPath',
            '--local' => 'setLocal',
            '--drush-coverage=' => 'setCoverageFile',
        ];
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

    public function coverageFile()
    {
        return $this->get(self::COVERAGE_FILE);
    }

    public function setCoverageFile($coverageFile)
    {
        return $this->set(self::COVERAGE_FILE, $coverageFile);
    }
}
