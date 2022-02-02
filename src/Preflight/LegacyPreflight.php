<?php

namespace Drush\Preflight;

use Drush\Drush;
use Drush\Config\Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
    public static function defineConstants(Environment $environment, $applicationPath): void
    {
        // 'define' is undesirable in that it will error if the same identifier
        // is defined more than once. Ideally we would inject the legacy preflight
        // object into the Preflight class, and wherever else it was needed,
        // and omit it for the integration tests. This is probably not practicable
        // at the moment, though.
        if (defined('DRUSH_REQUEST_TIME')) {
            return;
        }

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
    }

    /**
     * Include old code. It is an aspirational goal to remove or refactor
     * all of this into more modular, class-based code.
     */
    public static function includeCode($drushBasePath): void
    {
        // We still need preflight for drush_shutdown()
        require_once $drushBasePath . '/includes/preflight.inc';
        require_once $drushBasePath . '/includes/bootstrap.inc';
        require_once $drushBasePath . '/includes/drush.inc';
        require_once $drushBasePath . '/includes/batch.inc';
        require_once $drushBasePath . '/includes/output.inc';
        require_once $drushBasePath . '/includes/filesystem.inc';
        require_once $drushBasePath . '/includes/legacy.inc';
    }
}
