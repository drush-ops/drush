<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Drush\Log\Logger;
use League\Container\Container;
use Drush\Drush;
use Symfony\Component\Console\Application;
use Composer\Autoload\ClassLoader;
use Drush\Command\DrushCommandInfoAlterer;
use Psr\Container\ContainerInterface;
use League\Container\Container as DrushContainer;
use Drush\Drupal\DrupalKernelTrait;
use Drush\Config\DrushConfig;

/**
 * Find drush.services.yml files.
 *
 * This discovery class is used solely for backwards compatability with
 * Drupal modules that still use drush.services.ymls to define Drush
 * Commands, Generators & etc.; this mechanism is deprecated, though.
 * Modules should instead use the static factory `create` mechanism.
 */
class  LegacyServiceFinder
{
    // We get the discovery code we need from the DrupalKernelTrait.
    // We could also just move the code from there to here eventually,
    // as that trait won't be needed once this class is used exclusively.
    use DrupalKernelTrait;

    protected $drushServiceYamls = [];

    public function __construct(protected $moduleHandler, protected DrushConfig $drushConfig)
    {
    }

    public function getDrushServiceFiles()
    {
        if (empty($drushServiceYamls)) {
            $this->discoverDrushServiceProviders();
        }
        return $this->drushServiceYamls;
    }

    protected function getModuleFileNames()
    {
        $modules = $this->moduleHandler->getModuleList();
        $moduleFilenames = [];

        foreach ($modules as $module => $extension) {
            $moduleFilenames[$module] = $extension->getPathname();
        }

        return $moduleFilenames;
    }
}
