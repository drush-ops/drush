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
        $modules = $container->getParameter('container.modules');

        // The early ones we always load,
        $namespaces['Drush\\Dr\\early'] =  __DIR__ . '/Dr/early';
        if (isset($modules['system'])) {
            // The standard ones we only load when drupal is installed.
            $namespaces['Drush\\Dr\\standard'] =  __DIR__ . '/Dr/standard';
        }

        $container->setParameter('container.namespaces', $namespaces);
    }
}
