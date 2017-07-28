<?php
namespace Drush\Boot;

use Drush\Drush;
use Drush\Cache\CommandCache;

/**
 * Prepare our Dependency Injection Container
 */
class DependencyInjection
{
    /**
     * Set up our dependency injection container.
     */
    public static function initContainer($application, $config, $input = null, $output = null)
    {
        // Create default input and output objects if they were not provided
        if (!$input) {
            $input = new \Symfony\Component\Console\Input\StringInput('');
        }
        if (!$output) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        }
        // Set up our dependency injection container.
        $container = new \League\Container\Container();

        \Robo\Robo::configureContainer($container, $application, $config, $input, $output);
        $container->add('container', $container);

        static::addDrushServices($container);

        // Store the container in the \Drush object
        Drush::setContainer($container);
        \Robo\Robo::setContainer($container);

        static::alterServicesForDrush($container, $application);

        return $container;
    }

    protected static function addDrushServices($container)
    {
        // Override Robo's logger with our own
        $container->share('logger', 'Drush\Log\Logger')
          ->withArgument('output')
          ->withMethodCall('setLogOutputStyler', ['logStyler']);

        // Override Robo's formatter manager with our own
        // @todo not sure that we'll use this. Maybe remove it.
        $container->share('formatterManager', \Drush\Formatters\DrushFormatterManager::class)
            ->withMethodCall('addDefaultFormatters', [])
            ->withMethodCall('addDefaultSimplifiers', []);

        // Add some of our own objects to the container
        $container->share('bootstrap.default', 'Drush\Boot\EmptyBoot');
        $container->share('bootstrap.drupal6', 'Drush\Boot\DrupalBoot6');
        $container->share('bootstrap.drupal7', 'Drush\Boot\DrupalBoot7');
        $container->share('bootstrap.drupal8', 'Drush\Boot\DrupalBoot8');
        $container->share('bootstrap.manager', 'Drush\Boot\BootstrapManager')
          ->withArgument('bootstrap.default');
        $container->extend('bootstrap.manager')
          ->withMethodCall('add', ['bootstrap.drupal6'])
          ->withMethodCall('add', ['bootstrap.drupal7'])
          ->withMethodCall('add', ['bootstrap.drupal8']);
    }

    protected static function alterServicesForDrush($container, $application)
    {
        // Add our own callback to the hook manager
        $hookManager = $container->get('hookManager');
        $hookManager->addOutputExtractor(new \Drush\Backend\BackendResultSetter());
        // @todo: do we need both backend result setters? The one below should be removed at some point.
        $hookManager->add('annotatedcomand_adapter_backend_result', \Consolidation\AnnotatedCommand\Hooks\HookManager::EXTRACT_OUTPUT);

        // Install our command cache into the command factory
        // TODO: Create class-based implementation of our cache management functions.
        $cacheBackend = _drush_cache_get_object('factory');
        $commandCacheDataStore = new CommandCache($cacheBackend);

        $factory = $container->get('commandFactory');
        $factory->setIncludeAllPublicMethods(false);
        $factory->setDataStore($commandCacheDataStore);

        // It is necessary to set the dispatcher when using configureContainer
        $eventDispatcher = $container->get('eventDispatcher');
        $eventDispatcher->addSubscriber(new \Drush\Command\GlobalOptionsEventListener());
        $application->setDispatcher($eventDispatcher);
    }
}
