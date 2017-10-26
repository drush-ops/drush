<?php

namespace Drush\Boot;

use Psr\Log\LoggerInterface;

class DrupalBoot7 extends DrupalBoot
{

    public function validRoot($path)
    {
        if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
            // Drupal 7 root.
            // We check for the presence of 'modules/field/field.module' to differentiate this from a D6 site
            $candidate = 'includes/common.inc';
            if (file_exists($path . '/' . $candidate) && file_exists($path . '/misc/drupal.js') && file_exists($path . '/modules/field/field.module')) {
                return $candidate;
            }
        }
    }

    public function getVersion($drupal_root)
    {
        $path = $drupal_root . '/includes/bootstrap.inc';
        if (is_file($path)) {
            require_once $path;
            if (defined('VERSION')) {
                return VERSION;
            }
        }
    }

    public function getProfile()
    {
        return drupal_get_profile();
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
        return parent::bootstrapDrupalDatabaseValidate() && $this->bootstrapDrupalDatabaseHasTable('blocked_ips');
    }

    public function bootstrapDrupalDatabase()
    {
        drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
        parent::bootstrapDrupalDatabase();
    }

    public function bootstrapDrupalConfiguration()
    {
        drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

        // Unset drupal error handler and restore drush's one.
        restore_error_handler();

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

        parent::bootstrapDrupalFull();
    }
}
