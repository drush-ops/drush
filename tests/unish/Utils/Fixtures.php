<?php

namespace Unish\Utils;

use Drush\Config\Environment;
use Symfony\Component\Filesystem\Path;

trait Fixtures
{
    protected function fixturesDir(): string
    {
        return Path::join(dirname(__DIR__, 2), 'fixtures');
    }

    protected function homeDir(): string
    {
        return Path::join($this->fixturesDir(), 'home');
    }

    protected function siteDir(string $majorVersion = '8'): string
    {
        return Path::join($this->fixturesDir(), '/sites/d' . $majorVersion);
    }

    protected function environment($cwd = false): Environment
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
