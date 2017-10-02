<?php
namespace Drush;

use \Drush\Config\Environment;

trait FixtureFactory
{
    protected function fixturesDir()
    {
        return dirname(__DIR__) . '/fixtures';
    }

    protected function homeDir()
    {
        return $this->fixturesDir() . '/home';
    }

    // It is still an aspirational goal to add Drupal 7 support back to Drush. :P
    // For now, only Drupal 8 is supported.
    protected function siteDir($majorVersion = '8')
    {
        return $this->fixturesDir() . '/sites/d' . $majorVersion;
    }

    protected function environment($cwd = false)
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
