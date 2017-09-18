<?php

namespace Drush\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class BootstrapCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('bootstrap.manager')) {
            return;
        }

        $definition = $container->findDefinition(
            'bootstrap.manager'
        );

        $taggedServices = $container->findTaggedServiceIds(
            'bootstrap.boot'
        );
        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall(
                'add',
                array(new Reference($id))
            );
        }
    }
}
