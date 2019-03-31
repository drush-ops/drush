<?php
namespace Unish\Utils;

use \Drush\Config\Environment;
use Webmozart\PathUtil\Path;

trait Fixtures
{
    protected function fixturesDir()
    {
        return Path::join(dirname(dirname(__DIR__)), 'fixtures');
    }

    protected function homeDir()
    {
        return Path::join($this->fixturesDir(), 'home');
    }

    // It is still an aspirational goal to add Drupal 7 support back to Drush. :P
    // For now, only Drupal 8 is supported.
    protected function siteDir($majorVersion = '8')
    {
        return Path::join($this->fixturesDir(), '/sites/d' . $majorVersion);
    }

    protected function environment($cwd = false)
    {
        $fixturesDir = $this->fixturesDir();
        $home = $this->homeDir();
        if (!$cwd) {
            $cwd = $home;
        }
        $autoloadFile = Path::join(dirname(__DIR__), 'vendor/autoload.php');

        $environment = new Environment($home, $cwd, $autoloadFile);
        $environment
            ->setEtcPrefix($fixturesDir)
            ->setSharePrefix(Path::join($fixturesDir, 'usr'));

        return $environment;
    }
}
