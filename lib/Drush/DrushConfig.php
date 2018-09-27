<?php
namespace Drush;

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
}
