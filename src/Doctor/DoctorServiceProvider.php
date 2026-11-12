<?php

namespace Drush\Doctor;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\Image\Image;
use Drush\Commands\cache\CacheRebuildCommand;
use Drush\Commands\cache\CacheTagsCommand;
use Drush\Commands\core\BrowseCommand;
use Drush\Commands\core\ImageCommands;
use Drush\Commands\core\ImageDeriveCommand;
use Drush\Commands\core\ImageFlushCommand;
use Drush\Commands\core\PhpCommands;
use Drush\Commands\core\PhpEvalCommand;
use Drush\Commands\core\PhpScriptCommand;
use Drush\Commands\core\RouteCommand;
use Drush\Commands\core\WorkspacePublishCommand;
use Drush\Commands\entity\EntityDeleteCommand;
use Drush\Commands\entity\EntitySaveCommand;
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
            $classes = array_merge($classes, self::getClassesStandard($modules));
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
        $core = [
            PhpEvalCommand::class,
            PhpScriptCommand::class,
        ];

        return array_merge(
            $core,
            [],
        );
    }

    /**
     * @param array $modules
     *   The container.modules parameter, keyed by module name.
     */
    public static function getClassesStandard(array $modules): array
    {
        $core = [
            BrowseCommand::class,
            RouteCommand::class,
        ];

        $entity = [
            EntitySaveCommand::class,
            EntityDeleteCommand::class,
        ];

        $classes = array_merge(
            $core,
            $entity,
            FindClasses::findClasses('Drush\\Commands\\cache'),
            FindClasses::findClasses('Drush\\Commands\\queue'),
            FindClasses::findClasses('Drush\\Commands\\role'),
            FindClasses::findClasses('Drush\\Commands\\state'),
            FindClasses::findClasses('Drush\\Commands\\user'),
        );

        // These commands need a module that is not always installed.
        if (isset($modules['dblog'])) {
            $classes = array_merge($classes, FindClasses::findClasses('Drush\\Commands\\watchdog'));
        }
        if (isset($modules['image'])) {
            $classes[] = ImageDeriveCommand::class;
            $classes[] = ImageFlushCommand::class;
        }
        if (isset($modules['workspaces'])) {
            $classes[] = WorkspacePublishCommand::class;
        }

        $skip = [
            CacheRebuildCommand::class,
            UserLoginCommand::class,
        ];

        return array_diff($classes, $skip);
    }
}
