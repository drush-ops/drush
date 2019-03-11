<?php
namespace Drush;

use Dflydev\DotAccessData\Data;

/**
 * Provides minimal access to Drush configuration in a way that is
 * forward-compatible with the Consolidation\Config classes used
 * in Drush 9.
 */
class DrushConfig
{
    /**
     * Determine if a non-default config value exists in a non-default context.
     */
    public function has($key)
    {
        $contexts = drush_context_names();
        $contexts = array_filter($contexts, function ($item) { return $item != 'default'; });

        foreach ($contexts as $context) {
            $value = _drush_get_option($option, drush_get_context($context));

            if ($value !== NULL) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fetch a configuration value
     *
     * @param string $key Which config item to look up
     * @param string|null $default Value to use when $key does not exist
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return drush_get_option($key, $default);
    }

    /**
     * Set a config value
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        drush_set_option($key, $value);
    }

    /**
     * export returns a collection of all of the
     * configuration available in the config object, although
     * in truth this list is pared down to include only
     * those values that are common to Drush 8 and Drush 9.
     */
    public function export()
    {
        $data = new Data;
        $contextData = drush_get_merged_options();
        $cliData = drush_get_context('cli');
        foreach ($cliData as $key => $value) {
            $data->set("options.$key", $value);
            unset($contextData[$key]);
        }
        foreach ($contextData as $key => $value) {
            $data->set($key, $value);
        }
        return $data->export();
    }

    /**
     * Return the default value for a given configuration item.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function hasDefault($key)
    {
        $value = $this->getDefault($key);
        return $value != null;
    }

    /**
     * Return the default value for a given configuration item.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getDefault($key, $default = null)
    {
        return drush_get_option($key, $default, 'default');
    }

    /**
     * Set the default value for a configuration setting. This allows us to
     * set defaults either before or after more specific configuration values
     * are loaded. Keeping defaults separate from current settings also
     * allows us to determine when a setting has been overridden.
     *
     * @param string $key
     * @param string $value
     */
    public function setDefault($key, $value)
    {
        drush_set_default($key, $value);
    }

    /**
     * Determine whether we are in 'backend' mode
     */
    public function backend()
    {
        return drush_get_context('DRUSH_BACKEND');
    }

    /**
     * Determine whether we are in 'simulate' mode
     */
    public function simulate()
    {
        return drush_get_context('DRUSH_SIMULATE');
    }

    public function drushScript()
    {
        return DRUSH_COMMAND;
    }
}
