<?php
namespace Drush\Boot;

use Drush\Drush;

/**
 * Prepare to bootstrap Drupal
 *
 * - Determine the site to use
 * - Set up the DI container
 * - Start the bootstrap process
 */
class LegacyPreflight
{
    /**
     * Define legacy constants.
     */
    public static function defineConstants($drushBasePath)
    {
        define('DRUSH_REQUEST_TIME', microtime(TRUE));
        define('DRUSH_BASE_PATH', $drushBasePath);

        /*
         * @deprecated. Use Drush::getVersion().
         */
        define('DRUSH_VERSION', Drush::getVersion());

        /*
         * @deprecated. Use Drush::getMajorVersion().
         */
        define('DRUSH_MAJOR_VERSION', Drush::getMajorVersion());

        /*
         * @deprecated. Use Drush::getMinorVersion().
         */
        define('DRUSH_MINOR_VERSION', Drush::getMinorVersion());

        /*
         * @deprecated. Do not use
         */
        drush_set_context('argc', $GLOBALS['argc']);
        drush_set_context('argv', $GLOBALS['argv']);
        drush_set_context('DRUSH_VENDOR_PATH', $this->vendorPath);
        drush_set_context('DRUSH_CLASSLOADER', $this->loader);
    }

    /**
     * Include old code. It is an aspirational goal to remove or refactor
     * all of this into more modular, class-based code.
     */
    public static function includeCode($drushBasePath)
    {
        require_once $drushBasePath . '/includes/preflight.inc';
        require_once $drushBasePath . '/includes/bootstrap.inc';
        require_once $drushBasePath . '/includes/environment.inc';
        require_once $drushBasePath . '/includes/annotationcommand_adapter.inc';
        require_once $drushBasePath . '/includes/command.inc';
        require_once $drushBasePath . '/includes/drush.inc';
        require_once $drushBasePath . '/includes/backend.inc';
        require_once $drushBasePath . '/includes/batch.inc';
        require_once $drushBasePath . '/includes/context.inc';
        require_once $drushBasePath . '/includes/sitealias.inc';
        require_once $drushBasePath . '/includes/exec.inc';
        require_once $drushBasePath . '/includes/drupal.inc';
        require_once $drushBasePath . '/includes/output.inc';
        require_once $drushBasePath . '/includes/cache.inc';
        require_once $drushBasePath . '/includes/filesystem.inc';
        require_once $drushBasePath . '/includes/legacy.inc';
    }
}
