<?php
namespace Drush\Config;

use Consolidation\Config\Loader\ConfigLoaderInterface;

/**
 * Load the environment settings into our configuration
 */
class EnvironmentConfigLoader implements ConfigLoaderInterface
{
    protected $environment;

    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Convert loaded configuration into a simple php nested array.
     *
     * @return array
     */
    public function export()
    {
        return $this->environment->exportConfigData();
    }

    /**
     * Return the top-level keys in the exported data.
     *
     * @return array
     */
    public function keys()
    {
        $export = $this->export();
        return array_keys($export);
    }

    /**
     * Return a symbolic name for this configuration loader instance.
     */
    public function getSourceName()
    {
        return 'environment';
    }
}
