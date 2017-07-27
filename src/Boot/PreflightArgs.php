<?php
namespace Drush\Boot;

/**
 * Storage for arguments preprocessed during preflight.
 *
 * Holds @sitealias, if present, and a limited number of global options.
 */
class PreflightArgs implements PreflightArgsInterface
{
    /**
     * @var $args Remaining arguments not handled by the preprocessor
     */
    protected $args;

    protected $alias;

    protected $root;

    protected $configPath;

    protected $aliasPath;

    protected $commandPath;

    protected $isLocal;

    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function optionsWithValues()
    {
        return [
            '-r=' => 'setSelectedSite',
            '--root=' => 'setSelectedSite',
            '-c=' => 'setConfig',
            '--config=' => 'setConfigPath',
            '--alias-path=' => 'setAliasPath',
            '--include=' => 'setCommandPath',
            '--local' => 'setLocal',
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
        return $this->alias;
    }

    public function hasAlias()
    {
        return isset($this->alias);
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    public function selectedSite()
    {
        return $this->root;
    }

    public function setSelectedSite($root)
    {
        $this->root = $root;
        return $this;
    }

    public function configPath()
    {
        return $this->configPath;
    }

    public function setConfigPath($configPath)
    {
        $this->configPath = $configPath;
        return $this;
    }

    public function aliasPath()
    {
        return $this->aliasPath;
    }

    public function setAliasPath($aliasPath)
    {
        $this->aliasPath = $aliasPath;
        return $this;
    }

    public function commandPath()
    {
        return $this->commandPath;
    }

    public function setCommandPath($commandPath)
    {
        $this->commandPath = $commandPath;
        return $this;
    }

    public function isLocal()
    {
        return $this->isLocal;
    }

    public function setLocal($isLocal)
    {
        $this->isLocal = $isLocal;
        return $this;
    }
}
