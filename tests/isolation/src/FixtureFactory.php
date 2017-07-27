<?php
namespace Drush;

use \Drush\Config\Environment;

class FixtureFactory
{
    public function fixturesDir()
    {
        return dirname(__DIR__) . '/fixtures';
    }

    public function homeDir()
    {
        return $this->fixturesDir() . '/home';
    }

    public function environment($cwd = false)
    {
        $fixturesDir = $this->fixturesDir();
        $home = $this->homeDir();
        if (!$cwd) {
            $cwd = $home;
        }
        $autoloadFile = dirname(__DIR__) . '/vendor/autoload.php';

        $environment = new Environment($home, $cwd, $autoloadFile);
        $environment
            ->setEtcPrefix($fixturesDir)
            ->setSharePrefix($fixturesDir . '/usr');

        return $environment;
    }
}
