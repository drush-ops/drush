<?php

namespace Drush\Doctor;

use Drupal\Core\Config\Command\ConfigImportCommand as CoreConfigImportCommand;
use Drupal\Core\Config\ConfigImporterFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\Image\Image;
use Drupal\Core\Update\DatabaseUpdate;
use Drush\Commands\cache\CacheRebuildCommand;
use Drush\Commands\cache\CacheTagsCommand;
use Drush\Commands\config\ConfigDeleteCommand;
use Drush\Commands\config\ConfigGetCommand;
use Drush\Commands\config\ConfigSetCommand;
use Drush\Commands\config\ConfigStatusCommand;
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
use Drush\Doctor\Commands\config\ConfigImportCommand;
use Drush\Doctor\Commands\deploy\DeployCommand;
use Drush\Doctor\Commands\deploy\DeployHookCommand;
use Drush\Doctor\Commands\deploy\DeployHookStatusCommand;
use Drush\Doctor\Commands\deploy\DeployMarkCompleteCommand;
use Drush\Doctor\Commands\sql\SqlConnectCommand;
use Drush\Doctor\Commands\updatedb\UpdatedbCommand;
use Drush\Doctor\Commands\updatedb\UpdatedbStatusCommand;
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
            // Core has its own config:import after 12.1.
            if (!class_exists(CoreConfigImportCommand::class) && $container->hasDefinition(ConfigImporterFactory::class)) {
                $classes[] = ConfigImportCommand::class;
            }
            // The dr-only updatedb commands need core's DatabaseUpdate (11.5+).
            if ($container->hasDefinition(DatabaseUpdate::class)) {
                $classes[] = UpdatedbCommand::class;
                $classes[] = UpdatedbStatusCommand::class;
                $classes[] = DeployCommand::class;
            }
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

        // Needs only the connection info from settings.php.
        $sql = [
            SqlConnectCommand::class,
        ];

        return array_merge(
            $core,
            $sql,
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

        $config = [
            ConfigDeleteCommand::class,
            ConfigGetCommand::class,
            ConfigSetCommand::class,
            ConfigStatusCommand::class,
        ];

        $entity = [
            EntitySaveCommand::class,
            EntityDeleteCommand::class,
        ];

        $deploy = [
            DeployHookCommand::class,
            DeployHookStatusCommand::class,
            DeployMarkCompleteCommand::class,
        ];

        $classes = array_merge(
            $core,
            $config,
            $entity,
            $deploy,
            FindClasses::findClasses('Drush\\Commands\\cache'),
            FindClasses::findClasses('Drush\\Commands\\queue'),
            FindClasses::findClasses('Drush\\Commands\\state'),
        );

        // The user module cannot be uninstalled, but it is missing from the
        // container while site:install is still running and in kernel tests
        // that enable only system. Without this guard those containers fail
        // to compile, because the role commands autowire user services.
        if (isset($modules['user'])) {
            $classes = array_merge(
                $classes,
                FindClasses::findClasses('Drush\\Commands\\role'),
                FindClasses::findClasses('Drush\\Commands\\user'),
            );
        }

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
