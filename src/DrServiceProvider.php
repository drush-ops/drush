<?php

namespace Drush;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

class DrServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $container): void
    {
        // We add Drush to Drupal's container namespaces so that the Command
        // classes are loaded into dr without drush being an installed module.
        $namespaces = $container->getParameter('container.namespaces');
        $namespaces['Drush'] =  __DIR__;
        $container->setParameter('container.namespaces', $namespaces);
    }
}
