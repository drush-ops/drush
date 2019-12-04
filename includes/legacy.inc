<?php

/**
 * Temporary wrapper functions to ease usage across major Drupal versions.
 */

use Drupal\Core\Site\Settings;

/**
 * Gets and sets the path of the configured temporary directory.
 *
 * @return mixed|null
 *   A string containing the path to the temporary directory.
 */
function drush_file_directory_temp()
{
    if (drush_drupal_major_version() >= 9) {
        return \Drupal::service('file_system')->getTempDirectory();
    }
    return file_directory_temp();
}

/**
 * Returns the path of a configuration directory.
 *
 * Configuration directories are configured using $config_directories in
 * settings.php.
 *
 * @param string $type
 *   The type of config directory to return. Drupal core provides the
 *   CONFIG_SYNC_DIRECTORY constant to access the sync directory.
 *
 * @return string
 *   The configuration directory path.
 *
 * @throws \Exception
 */
function drush_config_get_config_directory($type) {
    if (drush_drupal_major_version() >= 9) {
        return Settings::get('config_sync_directory');
    }
    return config_get_config_directory($type);
}