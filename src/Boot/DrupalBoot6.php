<?php

/**
 * @file
 *   This file is required for recognizing a D6 root and showing deprecation error.
 */

namespace Drush\Boot;

use Psr\Log\LoggerInterface;

class DrupalBoot6 extends DrupalBoot
{

    public function validRoot($path)
    {
        if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
            // Drupal 6 root.
            // We check for the absence of 'modules/field/field.module' to differentiate this from a D7 site.
            // n.b. we want D5 and earlier to match here, if possible, so that we can print a 'not supported'
            // error during bootstrap.  If someone later adds a commandfile that adds a boot class for
            // Drupal 5, it will be tested first, so we shouldn't get here.
            $candidate = 'includes/common.inc';
            if (file_exists($path . '/' . $candidate) && file_exists($path . '/misc/drupal.js') && !file_exists($path . '/modules/field/field.module')) {
                return $candidate;
            }
        }
    }

    public function getVersion($drupal_root)
    {
        $path = $drupal_root . '/modules/system/system.module';
        if (is_file($path)) {
            require_once $path;
            if (defined('VERSION')) {
                return VERSION;
            }
        }
    }

    public function getProfile()
    {
        return variable_get('install_profile', 'standard');
    }

    public function addLogger()
    {
    }

    public function bootstrapDrupalCore($drupal_root)
    {
        define('DRUPAL_ROOT', $drupal_root);
        require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
        $core = DRUPAL_ROOT;

        return $core;
    }

    public function bootstrapDrupalDatabaseValidate()
    {
        return parent::bootstrapDrupalDatabaseValidate() && $this->bootstrapDrupalDatabaseHasTable('cache');
    }

    public function bootstrapDrupalDatabase()
    {
        drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
        parent::bootstrapDrupalDatabase();
    }

    public function bootstrapDrupalConfiguration()
    {
        drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

        parent::bootstrapDrupalConfiguration();
    }

    public function bootstrapDrupalFull()
    {
        if (!drush_get_context('DRUSH_QUIET', false)) {
            ob_start();
        }
        drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
        if (!drush_get_context('DRUSH_QUIET', false)) {
            ob_end_clean();
        }

        // Unset drupal error handler and restore drush's one.
        restore_error_handler();

        parent::bootstrapDrupalFull();
    }
}
