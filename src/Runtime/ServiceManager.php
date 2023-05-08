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
 * Manage Drush services.
 *
 * This class manages the various services / plugins supported by Drush.
 * The primary examples of these include:
 * 
 *   - Command files
 *   - Hooks
 *   - Symfony Console commands
 *   - Command info alterers
 *   - Generators
 * 
 * Most services are discovered via the PSR-4 discovery mechanism. Legacy
 * services are injected into this object by the bootstrap handler
 * (DrushBoot8) using the LegacyServiceFinder and LegacyServiceInstantiator
 * classes.
 * 
 * TODO: That's the intention, anyway. For now, we just stuff gererators here.
 */
class  ServiceManager
{
    protected $generators = [];

    public function __construct(protected ClassLoader $autoloader)
    {
    }

    public function getGenerators()
    {
        return $this->generators;
    }

    public function injectGenerators($additionalGenerators)
    {
        $this->generators = [
            ...$this->generators,
            ...$additionalGenerators
        ];

    }
}
