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

    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function optionsWithValues()
    {
        return [
            '-r' => 'setSelectedSite',
            '--root' => 'setSelectedSite',
            '-c' => 'setConfig',
            '--config' => 'setConfigPath',
            '--alias-path' => 'setAliasPath',
        ];
    }

    /**
     * @inheritdoc
     */
    public function args()
    {
        return $this->args;
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
}
