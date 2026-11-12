<?php

namespace Drush\Doctor;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drush\Commands\cache\CacheTagsCommand;
use Drush\Commands\core\PhpCommands;
use Drush\Commands\state\StateGetCommand;
use Drush\Commands\user\UserInformationCommand;
use Symfony\Component\DependencyInjection\Definition;

class DoctorServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $container): void
    {
        // Add Drush commands Drupal's container so that dr loads them.
        $modules = $container->getParameter('container.modules');
        $classes = self::getClassesEarly();
        if (isset($modules['system'])) {
            // Drupal is installed, so add classes that require it.
            $classes = array_merge($classes, self::getClassesStandard());
        }

        // These definitions are further processed by AddConsoleCommandPass.
        foreach ($classes as $className) {
            $definition = new Definition($className)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('console.command');
            $container->setDefinition($className, $definition);
        }
    }

    public static function getClassesEarly(): array
    {
        return [
            // @todo add more.
            StateGetCommand::class,
        ];
    }

    public static function getClassesStandard(): array
    {
        return [
            // @todo add more.
            UserInformationCommand::class,
        ];
    }
}
