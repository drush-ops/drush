<?php
namespace Drush\Preflight;

use Drush\Drush;
use Drush\Config\Environment;
use Webmozart\PathUtil\Path;

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
    public static function defineConstants(Environment $environment, $applicationPath)
    {
        $applicationPath = Path::makeAbsolute($applicationPath, $environment->cwd());

        define('DRUSH_REQUEST_TIME', microtime(true));

        /*
         * @deprecated. Use $config->get('drush.base-dir') instead.
         */
        define('DRUSH_BASE_PATH', $environment->drushBasePath());

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
         * @deprecated.
         */
        define('DRUSH_COMMAND', $applicationPath);

        /*
         * @deprecated. Use $config->get('env.cwd') instead.
         */
        drush_set_context('DRUSH_OLDCWD', $environment->cwd());

        /*
         * @deprecated. Do not use
         */
        drush_set_context('argc', $GLOBALS['argc']);
        drush_set_context('argv', $GLOBALS['argv']);

        /*
         * @deprecated. Use $config->get('drush.vendor-dir') instead.
         */
        drush_set_context('DRUSH_VENDOR_PATH', $environment->vendorPath());

        /*
         * @deprecated. Use $environment->loader() instead.
         */
        drush_set_context('DRUSH_CLASSLOADER', $environment->loader());
    }

    public static function setContexts(Environment $environment)
    {
        /*
         * Obsolete. Presumed to be unnecessary; available in Environment if needed
         * (just add a getter method).
         */
        // drush_set_context('ETC_PREFIX', $environment->...);
        // drush_set_context('SHARE_PREFIX', $environment->...);

        /*
         * @deprecated. Use $config->get('drush.docs-dir') instead.
         */
        drush_set_context('DRUSH_BASE_PATH', $environment->docsPath());

        /*
         * @deprecated. Use $config->get('drush.system-dir') instead.
         */
        drush_set_context('DRUSH_SITE_WIDE_CONFIGURATION', $environment->systemConfigPath());

        /*
         * @deprecated. Use $config->get('drush.system-command-dir') instead.
         */
        drush_set_context('DRUSH_SITE_WIDE_COMMANDFILES', $environment->systemCommandFilePath());

        /*
         * @deprecated. Use $config->get('drush.user-dir') instead.
         */
        drush_set_context('DRUSH_PER_USER_CONFIGURATION', $environment->userConfigPath());
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
