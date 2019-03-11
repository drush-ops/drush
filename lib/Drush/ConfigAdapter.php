<?php
namespace Drush;

use Consolidation\Config\ConfigInterface;

/**
 * Note that DrushConfig deliberately does NOT implement the interface
 * Consolidation\Config\ConfigInterface because consolidation/config
 * is NOT a requirement of Drush 8. DrushConfig must therefore work in
 * the absence of consolidation/config.
 *
 * Drush 8 uses some components optionally, if they are available
 * (e.g. the AliasManager and the ProcessManager). Drush never calls
 * these directly, but makes them available to Drush extensions that
 * want to use them. In these instances, the needed dependencies should
 * be required in the composer.json of the extension that uses it.
 *
 * This adapter simply converts DrushConfig into an equivalent object
 * that implements ConfigInterface, so that it may be passed to
 * objects that typehint their parameters as ConfigInterface.
 */
class ConfigAdapter implements ConfigInterface
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function has($key)
    {
        return $this->config->has($key);
    }

    /**
     * @inheritdoc
     */
    public function get($key, $defaultFallback = null)
    {
        return $this->config->get($key, $defaultFallback);
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value)
    {
        $this->config->set($key, $value);
    }

    /**
     * @inheritdoc
     */
    public function import($data)
    {
        return $this->config->import($data);
    }

    /**
     * @inheritdoc
     */
    public function replace($data)
    {
        $this->config->replace($data);
    }

    /**
     * @inheritdoc
     */
    public function combine($data)
    {
        return $this->config->combine($data);
    }

    /**
     * @inheritdoc
     */
    public function export()
    {
        return $this->config->export();
    }

    /**
     * @inheritdoc
     */
    public function hasDefault($key)
    {
        return $this->config->hasDefault($key);
    }

    /**
     * @inheritdoc
     */
    public function getDefault($key, $defaultFallback = null)
    {
        return $this->config->getDefault($key, $defaultFallback);
    }

    /**
     * @inheritdoc
     */
    public function setDefault($key, $value)
    {
        return $this->setDefault($key, $value);
    }
}
