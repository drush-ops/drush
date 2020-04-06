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
