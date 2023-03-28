<?php

declare(strict_types=1);

namespace Drush\Preflight;

use Drush\Config\Environment;
use Drush\Drush;
use Symfony\Component\Filesystem\Path;

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
