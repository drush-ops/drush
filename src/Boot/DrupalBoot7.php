<?php

namespace Drush\Boot;

use Psr\Log\LoggerInterface;

class DrupalBoot7 extends DrupalBoot
{

    public function valid_root($path)
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

    public function get_version($drupal_root)
    {
        $path = $drupal_root . '/includes/bootstrap.inc';
        if (is_file($path)) {
            require_once $path;
            if (defined('VERSION')) {
                return VERSION;
            }
        }
    }

    public function get_profile()
    {
        return drupal_get_profile();
    }

    public function add_logger()
    {
    }

    public function contrib_modules_paths()
    {
        return array(
        $this->conf_path() . '/modules',
        'sites/all/modules',
        );
    }

    public function contrib_themes_paths()
    {
        return array(
        $this->conf_path() . '/themes',
        'sites/all/themes',
        );
    }

    public function bootstrap_drupal_core($drupal_root)
    {
        define('DRUPAL_ROOT', $drupal_root);
        require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
        $core = DRUPAL_ROOT;

        return $core;
    }

    public function bootstrap_drupal_database_validate()
    {
        return parent::bootstrap_drupal_database_validate() && $this->bootstrap_drupal_database_has_table('blocked_ips');
    }

    public function bootstrap_drupal_database()
    {
        drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
        parent::bootstrap_drupal_database();
    }

    public function bootstrap_drupal_configuration()
    {
        drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

        // Unset drupal error handler and restore drush's one.
        restore_error_handler();

        parent::bootstrap_drupal_configuration();
    }

    public function bootstrap_drupal_full()
    {
        if (!drush_get_context('DRUSH_QUIET', false)) {
            ob_start();
        }
        drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
        if (!drush_get_context('DRUSH_QUIET', false)) {
            ob_end_clean();
        }

        parent::bootstrap_drupal_full();
    }
}
