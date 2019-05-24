<?php
namespace Drush\Runtime;

use Drush\Command\GlobalOptionsEventListener;
use Drush\Drush;
use Drush\Cache\CommandCache;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application;
use Consolidation\Config\ConfigInterface;
use Composer\Autoload\ClassLoader;
use League\Container\ContainerInterface;
use Consolidation\SiteAlias\SiteAliasManager;
use Drush\Command\DrushCommandInfoAlterer;
use Consolidation\Config\Util\ConfigOverlay;
use Drush\Config\DrushConfig;
use Drush\SiteAlias\ProcessManager;

/**
 * Prepare our Dependency Injection Container
 */
class DependencyInjection
{
    protected $handlers = [];

    public function desiredHandlers($handlerList)
    {
        $this->handlers = $handlerList;
    }

    /**
     * Set up our dependency injection container.
     */
    public function initContainer(
        Application $application,
        ConfigInterface $config,
        InputInterface $input,
        OutputInterface $output,
        ClassLoader $loader,
        DrupalFinder $drupalFinder,
        SiteAliasManager $aliasManager
    ) {

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

        $this->addDrushServices($container, $loader, $drupalFinder, $aliasManager, $config);

        // Store the container in the \Drush object
        Drush::setContainer($container);

        // Change service definitions as needed for our application.
        $this->alterServicesForDrush($container, $application);

        // Inject needed services into our application object.
        $this->injectApplicationServices($container, $application);

        return $container;
    }

    /**
     * Make sure we are notified on exit, and when bad things happen.
     */
    public function installHandlers($container)
    {
        foreach ($this->handlers as $handlerId) {
            $handler = $container->get($handlerId);
            $handler->installHandler();
        }
    }

    protected function addDrushServices(ContainerInterface $container, ClassLoader $loader, DrupalFinder $drupalFinder, SiteAliasManager $aliasManager, DrushConfig $config)
    {
        // Override Robo's logger with our own
        $container->share('logger', 'Drush\Log\Logger')
          ->withArgument('output')
          ->withMethodCall('setLogOutputStyler', ['logStyler']);

        $container->share('loader', $loader);
        $container->share('site.alias.manager', $aliasManager);

        // Fetch the runtime config, where -D et. al. are stored, and
        // add a reference to it to the container.
        $container->share('config.runtime', $config->getContext(ConfigOverlay::PROCESS_CONTEXT));

        // Override Robo's formatter manager with our own
        // @todo not sure that we'll use this. Maybe remove it.
        $container->share('formatterManager', \Drush\Formatters\DrushFormatterManager::class)
            ->withMethodCall('addDefaultFormatters', [])
            ->withMethodCall('addDefaultSimplifiers', []);

        // Add some of our own objects to the container
        $container->share('bootstrap.drupal8', 'Drush\Boot\DrupalBoot8');
        $container->share('bootstrap.manager', 'Drush\Boot\BootstrapManager')
            ->withMethodCall('setDrupalFinder', [$drupalFinder]);
        // TODO: Can we somehow add these via discovery (e.g. backdrop extension?)
        $container->extend('bootstrap.manager')
            ->withMethodCall('add', ['bootstrap.drupal8']);
        $container->share('bootstrap.hook', 'Drush\Boot\BootstrapHook')
          ->withArgument('bootstrap.manager');
        $container->share('tildeExpansion.hook', 'Drush\Runtime\TildeExpansionHook');
        $container->share('process.manager', ProcessManager::class)
            ->withMethodCall('setConfig', ['config'])
            ->withMethodCall('setConfigRuntime', ['config.runtime']);
        $container->share('redispatch.hook', 'Drush\Runtime\RedispatchHook')
            ->withArgument('process.manager');

        // Robo does not manage the command discovery object in the container,
        // but we will register and configure one for our use.
        // TODO: Some old adapter code uses this, but the Symfony dispatcher does not.
        // See Application::commandDiscovery().
        $container->share('commandDiscovery', 'Consolidation\AnnotatedCommand\CommandFileDiscovery')
            ->withMethodCall('addSearchLocation', ['CommandFiles'])
            ->withMethodCall('setSearchPattern', ['#.*(Commands|CommandFile).php$#']);

        // Error and Shutdown handlers
        $container->share('errorHandler', 'Drush\Runtime\ErrorHandler');
        $container->share('shutdownHandler', 'Drush\Runtime\ShutdownHandler');

        // Add inflectors. @see \Drush\Boot\BaseBoot::inflect
        $container->inflector(\Drush\Boot\AutoloaderAwareInterface::class)
            ->invokeMethod('setAutoloader', ['loader']);
        $container->inflector(\Consolidation\SiteAlias\SiteAliasManagerAwareInterface::class)
            ->invokeMethod('setSiteAliasManager', ['site.alias.manager']);
        $container->inflector(\Consolidation\SiteProcess\ProcessManagerAwareInterface::class)
            ->invokeMethod('setProcessManager', ['process.manager']);
    }

    protected function alterServicesForDrush(ContainerInterface $container, Application $application)
    {
        // Add our own callback to the hook manager
        $hookManager = $container->get('hookManager');
        $hookManager->addCommandEvent(new GlobalOptionsEventListener());
        $hookManager->addInitializeHook($container->get('redispatch.hook'));
        $hookManager->addInitializeHook($container->get('bootstrap.hook'));
        $hookManager->addPreValidator($container->get('tildeExpansion.hook'));
        $hookManager->addOutputExtractor(new \Drush\Backend\BackendResultSetter());

        // Install our command cache into the command factory
        // TODO: Create class-based implementation of our cache management functions.
        $cacheBackend = _drush_cache_get_object('factory');
        $commandCacheDataStore = new CommandCache($cacheBackend);

        $factory = $container->get('commandFactory');
        $factory->setIncludeAllPublicMethods(false);
        $factory->setDataStore($commandCacheDataStore);
        $factory->addCommandInfoAlterer(new DrushCommandInfoAlterer());

        $commandProcessor = $container->get('commandProcessor');
        $commandProcessor->setPassExceptions(true);

        ProcessManager::addTransports($container->get('process.manager'));
    }

    protected function injectApplicationServices(ContainerInterface $container, Application $application)
    {
        $application->setLogger($container->get('logger'));
        $application->setBootstrapManager($container->get('bootstrap.manager'));
        $application->setAliasManager($container->get('site.alias.manager'));
        $application->setRedispatchHook($container->get('redispatch.hook'));
        $application->setTildeExpansionHook($container->get('tildeExpansion.hook'));
        $application->setDispatcher($container->get('eventDispatcher'));
        $application->setConfig($container->get('config'));
    }
}
