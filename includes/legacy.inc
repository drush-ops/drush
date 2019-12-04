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

/**
 * Gets the name of the currently active installation profile.
 *
 * When this function is called during Drupal's initial installation process,
 * the name of the profile that's about to be installed is stored in the global
 * installation state. At all other times, the "install_profile" setting will be
 * available in container as a parameter.
 *
 * @return string|null
 *   The name of the installation profile or NULL if no installation profile is
 *   currently active. This is the case for example during the first steps of
 *   the installer or during unit tests.
 *
 * @deprecated in Drupal 8.3.0, will be removed before Drupal 9.0.0.
 *   Use the install_profile container parameter or \Drupal::installProfile()
 *   instead. If you are accessing the value before it is written to
 *   configuration during the installer use the $install_state global. If you
 *   need to access the value before container is available you can use
 *   BootstrapConfigStorageFactory to load the value directly from
 *   configuration.
 *
 * @see https://www.drupal.org/node/2538996
 */
function drush_drupal_get_profile()
{
    if (drush_drupal_major_version() >= 9) {
        return \Drupal::installProfile();
    }
    return drupal_get_profile();
}