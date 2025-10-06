<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Composer\Autoload\ClassLoader;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Consolidation\Config\ConfigInterface;
use Consolidation\Config\Util\ConfigOverlay;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Drush\Application;
use Drush\Boot\BootstrapHook;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBoot8;
use Drush\Cache\CommandCache;
use Drush\Command\DrushCommandInfoAlterer;
use Drush\Command\GlobalOptionsEventListener;
use Drush\Config\DrushConfig;
use Drush\DrupalFinder\DrushDrupalFinder;
use Drush\Drush;
use Drush\Formatters\DrushFormatterManager;
use Drush\Formatters\EntityToArraySimplifier;
use Drush\Log\DrushLoggerManager;
use Drush\Log\Logger;
use Drush\SiteAlias\ProcessManager;
use Drush\Symfony\DrushStyleInjector;
use League\Container\Container;
use League\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Robo\Robo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prepare our Dependency Injection Container
 */
class DependencyInjection
{
    const FORMATTER_MANAGER = 'formatterManager';
    const SITE_ALIAS_MANAGER = 'site.alias.manager';
    const BOOTSTRAP_MANAGER = 'bootstrap.manager';
    const PROCESS_MANAGER = 'process.manager';
    const LOADER = 'loader';
    protected array $handlers = [];

    public function desiredHandlers($handlerList): void
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
        DrushDrupalFinder $drupalFinder,
        SiteAliasManager $aliasManager
    ): Container {

        // Set up our dependency injection container.
        $container = new Container();

        // With league/container 3.x, first call wins, so add Drush services first.
        $this->addDrushServices($container, $loader, $drupalFinder, $aliasManager, $config, $output, $input);

        // Robo has the same signature for configureContainer in 1.x, 2.x and 3.x.
        Robo::configureContainer($container, $application, $config, $input, $output);
        $container->add('container', $container);
        $container->add(\Psr\Container\ContainerInterface::class, 'container'); // For autowiring

        // Store the container in the \Drush object
        Drush::setContainer($container);

        // Change service definitions as needed for our application.
        $this->alterServicesForDrush($container, $application, $input, $output);

        // Inject needed services into our application object.
        $this->injectApplicationServices($container, $application);

        return $container;
    }

    /**
     * Make sure we are notified on exit, and when bad things happen.
     */
    public function installHandlers($container): void
    {
        foreach ($this->handlers as $handlerId) {
            $handler = $container->get($handlerId);
            $handler->installHandler();
        }
    }

    // Add Drush Services to league/container 3.x
    protected function addDrushServices(Container $container, ClassLoader $loader, DrushDrupalFinder $drupalFinder, SiteAliasManager $aliasManager, DrushConfig $config, OutputInterface $output, InputInterface $input): void
    {
        // Override Robo's logger with a LoggerManager that delegates to the Drush logger.
        Robo::addShared($container, 'logger', DrushLoggerManager::class)
            ->addMethodCall('setLogOutputStyler', ['logStyler'])
            ->addMethodCall('add', ['drush', new Logger($output)]);
        Robo::addShared($container, LoggerInterface::class, 'logger');  // For autowiring

        Robo::addShared($container, self::LOADER, $loader);
        Robo::addShared($container, ClassLoader::class, self::LOADER);  // For autowiring
        Robo::addShared($container, self::SITE_ALIAS_MANAGER, $aliasManager);
        Robo::addShared($container, SiteAliasManagerInterface::class, self::SITE_ALIAS_MANAGER);  // For autowiring

        // Fetch the runtime config, where -D et. al. are stored, and
        // add a reference to it to the container.
        Robo::addShared($container, 'config.runtime', $config->getContext(ConfigOverlay::PROCESS_CONTEXT));

        // Override Robo's formatter manager with our own
        // @todo not sure that we'll use this. Maybe remove it.
        Robo::addShared($container, self::FORMATTER_MANAGER, DrushFormatterManager::class)
            ->addMethodCall('addDefaultFormatters', [])
            ->addMethodCall('addDefaultSimplifiers', [])
            ->addMethodCall('addSimplifier', [new EntityToArraySimplifier()]);
        Robo::addShared($container, FormatterManager::class, self::FORMATTER_MANAGER);  // For autowiring

        // Add some of our own objects to the container
        Robo::addShared($container, 'service.manager', ServiceManager::class)
            ->addArgument(self::LOADER)
            ->addArgument('config')
            ->addArgument('logger');
        Robo::addShared($container, 'bootstrap.drupal8', DrupalBoot8::class)
            ->addArgument('service.manager')
            ->addArgument(self::LOADER);
        Robo::addShared($container, self::BOOTSTRAP_MANAGER, BootstrapManager::class)
            ->addMethodCall('setDrupalFinder', [$drupalFinder])
            ->addMethodCall('add', ['bootstrap.drupal8']);
        Robo::addShared($container, BootstrapManager::class, self::BOOTSTRAP_MANAGER); // For autowiring
        Robo::addShared($container, 'bootstrap.hook', BootstrapHook::class)
          ->addArgument(self::BOOTSTRAP_MANAGER);
        Robo::addShared($container, 'tildeExpansion.hook', TildeExpansionHook::class);
        Robo::addShared($container, self::PROCESS_MANAGER, ProcessManager::class)
            ->addMethodCall('setConfig', ['config'])
            ->addMethodCall('setConfigRuntime', ['config.runtime'])
            ->addMethodCall('setDrupalFinder', [$drupalFinder]);
        Robo::addShared($container, ProcessManager::class, self::PROCESS_MANAGER); // For autowiring
        Robo::addShared($container, 'redispatch.hook', RedispatchHook::class)
            ->addArgument(self::PROCESS_MANAGER);
        ;

        // Robo does not manage the command discovery object in the container,
        // but we will register and configure one for our use.
        // TODO: Some old adapter code uses this, but the Symfony dispatcher does not.
        // See Application::commandDiscovery().
        Robo::addShared($container, 'commandDiscovery', CommandFileDiscovery::class)
            ->addMethodCall('addSearchLocation', ['CommandFiles'])
            ->addMethodCall('setSearchPattern', ['#.*(Commands|CommandFile).php$#']);

        // Error and Shutdown handlers
        Robo::addShared($container, 'errorHandler', ErrorHandler::class);
        Robo::addShared($container, 'shutdownHandler', ShutdownHandler::class);

        // Add inflectors. @see \Drush\Boot\BaseBoot::inflect
        $container->inflector(SiteAliasManagerAwareInterface::class)
            ->invokeMethod('setSiteAliasManager', [self::SITE_ALIAS_MANAGER]);
        $container->inflector(ProcessManagerAwareInterface::class)
            ->invokeMethod('setProcessManager', [self::PROCESS_MANAGER]);
    }

    protected function alterServicesForDrush($container, Application $application, InputInterface $input, OutputInterface $output): void
    {
        $paramInjection = $container->get('parameterInjection');
        $paramInjection->register(SymfonyStyle::class, new DrushStyleInjector());

        // Alias the dispatcher service that is defined in \Robo\Robo::configureContainer.
        Robo::addShared($container, EventDispatcherInterface::class, 'eventDispatcher');  // For autowiring

        // Alias the config service that is defined in \Robo\Robo::configureContainer.
        Robo::addShared($container, DrushConfig::class, 'config');  // For autowiring

        // Add our own callback to the hook manager
        $hookManager = $container->get('hookManager');
        $hookManager->addCommandEvent(new GlobalOptionsEventListener());
        $hookManager->addInitializeHook($container->get('redispatch.hook'));
        $hookManager->addInitializeHook($container->get('bootstrap.hook'));
        $hookManager->addPreValidator($container->get('tildeExpansion.hook'));

        $factory = $container->get('commandFactory');
        $factory->setIncludeAllPublicMethods(false);
        $factory->setIgnoreCommandsInTraits(true);
        $factory->addCommandInfoAlterer(new DrushCommandInfoAlterer());

        $commandProcessor = $container->get('commandProcessor');
        $commandProcessor->setPassExceptions(true);

        ProcessManager::addTransports($container->get(self::PROCESS_MANAGER));
    }

    protected function injectApplicationServices($container, Application $application): void
    {
        $application->setLogger($container->get('logger'));
        $application->setBootstrapManager($container->get(self::BOOTSTRAP_MANAGER));
        $application->setAliasManager($container->get(self::SITE_ALIAS_MANAGER));
        $application->setRedispatchHook($container->get('redispatch.hook'));
        $application->setTildeExpansionHook($container->get('tildeExpansion.hook'));
        $application->setDispatcher($container->get('eventDispatcher'));
        $application->setConfig($container->get('config'));
        $application->setServiceManager($container->get('service.manager'));
    }
}
