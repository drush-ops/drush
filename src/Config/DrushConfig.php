<?php
namespace Drush\Config;

use Consolidation\Config\Util\ConfigOverlay;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

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

    public function cache()
    {
        $candidates = [
            Path::join($this->home(), '.drush/cache'),
            Path::join($this->tmp(), 'drush-' . $this->user() . '/cache'),
        ];

        $fs = new Filesystem();
        foreach ($candidates as $candidate) {
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
