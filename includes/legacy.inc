<?php

/**
 * Temporary wrapper functions to ease usage across major Drupal versions.
 */

use Drupal\Core\Site\Settings;

/**
 * Returns the path of a configuration directory.
 *
 * Configuration directories are configured using $config_directories in
 * settings.php.
 *
 * @param string $type
 *   The type of config directory to return.
 *
 * @return string
 *   The configuration directory path.
 *
 * @throws \Exception
 */
function drush_config_get_config_directory($type = 'sync') {
    if (drush_drupal_major_version() >= 9) {
        return Settings::get('config_sync_directory');
    }
    return config_get_config_directory($type);
}