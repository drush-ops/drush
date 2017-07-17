<?php
namespace Drush\Config;

use Webmozart\PathUtil\Path;

/**
 * Locate configuration files
 */
class ConfigLocater
{
    public function __construct()
    {
    }

    public function config()
    {
        $roboConfig = new \Robo\Config(); // TODO: make a global Drush config class derived from \Robo\Config. Then use $drushConfig here instead of $roboConfig
        return $roboConfig;
    }

    public function addUserConfig($configPath, $home)
    {

    }

    public function addDrushConfig($drushProjectDir)
    {

    }

    public function addAliasConfig($alias, $aliasPath, $home)
    {

    }

    public function addSiteConfig($siteRoot)
    {
        // There might not be a site
        if (!$siteRoot) {
            return;
        }
    }
}
