<?php

declare(strict_types=1);

namespace Drush\Config;

use Consolidation\Config\Util\ConfigOverlay;
use Robo\Config\Config;

// TODO: Not sure if we should have a reference to PreflightArgs here.
// Maybe these constants should be in config, and PreflightArgs can
// reference them from there as well.

/**
 * Accessors for common Drush config keys.
 */
class DrushConfig extends ConfigOverlay
{
    public function cwd()
    {
        return $this->get('env.cwd');
    }

    public function home()
    {
        return $this->get('env.home');
    }

    public function user()
    {
        return $this->get('env.user');
    }

    public function isWindows()
    {
        return $this->get('env.is-windows');
    }

    public function tmp()
    {
        return $this->get('env.tmp');
    }

    /**
     * Return the path to this Drush
     */
    public function drushScript()
    {
        return $this->get('runtime.drush-script', 'drush');
    }

    /**
     * Return 'true' if we are in simulated mode.
     */
    public function simulate()
    {
        return $this->get(Config::SIMULATE);
    }

    /**
     * Return the list of paths to active Drush configuration files.
     */
    public function configPaths(): array
    {
        return $this->get('runtime.config.paths', []);
    }
}
