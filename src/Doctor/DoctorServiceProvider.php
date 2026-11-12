<?php

namespace Drush\Doctor;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drush\Commands\cache\CacheRebuildCommand;
use Drush\Commands\cache\CacheTagsCommand;
use Drush\Commands\core\PhpCommands;
use Drush\Commands\state\StateGetCommand;
use Drush\Commands\user\UserLoginCommand;
use Grasmash\YamlCli\Command\GetValueCommand;
use Grasmash\YamlCli\Command\LintCommand;
use Grasmash\YamlCli\Command\UnsetKeyCommand;
use Grasmash\YamlCli\Command\UpdateKeyCommand;
use Grasmash\YamlCli\Command\UpdateValueCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Definition;

class DoctorServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $container): void
    {
        //
        $container->addAliases([LoggerInterface::class => 'logger.channel.default']);

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
            GetValueCommand::class,
            LintCommand::class,
            UpdateKeyCommand::class,
            UnsetKeyCommand::class,
            UpdateValueCommand::class,
        ];
    }

    public static function getClassesStandard(): array
    {
        // @todo add more.
        $classes = array_merge(
            FindClasses::findClasses('Commands\\cache'),
            FindClasses::findClasses('Commands\\queue'),
            FindClasses::findClasses('Commands\\role'),
            FindClasses::findClasses('Commands\\state'),
            FindClasses::findClasses('Commands\\user'),
        );

        $skip = [
            CacheRebuildCommand::class,
            UserLoginCommand::class,
        ];

        return array_diff($classes, $skip);
    }
}
