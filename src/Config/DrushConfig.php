<?php
namespace Drush\Config;

use Consolidation\Config\Util\ConfigOverlay;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

// TODO: Not sure if we should have a reference to PreflightArgs here.
// Maybe these constants should be in config, and PreflightArgs can
// reference them from there as well.
use Drush\Preflight\PreflightArgs;

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
        return $this->get(\Robo\Config\Config::SIMULATE);
    }

    /**
     * Return 'true' if we are in backend mode.
     */
    public function backend()
    {
        return $this->get(PreflightArgs::BACKEND);
    }

    /**
     * Return the list of paths to active Drush configuration files.
     * @return array
     */
    public function configPaths()
    {
        return $this->get('runtime.config.paths', []);
    }

    public function cache()
    {
        $candidates = [
            $this->get('drush.paths.cache-directory'),
            Path::join($this->home(), '.drush/cache'),
            Path::join($this->tmp(), 'drush-' . $this->user() . '/cache'),
        ];

        $fs = new Filesystem();
        foreach (array_filter($candidates) as $candidate) {
            try {
                $fs->mkdir($candidate);
                return $candidate;
            } catch (IOException $ioException) {
                // Do nothing. Jump to the next candidate.
            }
        }
        throw new \Exception('Cannot create the Drush cache directory. Tried next candidates: ' . implode(', ', $candidates));
    }
}
