<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Drush\Formatters\EntityToArraySimplifier;
use Drush\Log\Logger;
use League\Container\Container;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Robo\Robo;
use Drush\Formatters\DrushFormatterManager;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Drush\Command\GlobalOptionsEventListener;
use Drush\Drush;
use Drush\Symfony\DrushStyleInjector;
use Drush\Cache\CommandCache;
use Drush\DrupalFinder\DrushDrupalFinder;
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

        // Create default input and output objects if they were not provided
        if (!$input) {
            $input = new StringInput('');
        }
        if (!$output) {
            $output = new ConsoleOutput();
        }
        // Set up our dependency injection container.
        $container = new Container();

        // With league/container 3.x, first call wins, so add Drush services first.
        $this->addDrushServices($container, $loader, $drupalFinder, $aliasManager, $config, $output);

        // Robo has the same signature for configureContainer in 1.x, 2.x and 3.x.
        Robo::configureContainer($container, $application, $config, $input, $output);
        $container->add('container', $container);

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
    public function installHandlers($container): void
    {
        foreach ($this->handlers as $handlerId) {
            $handler = $container->get($handlerId);
            $handler->installHandler();
        }
    }

    // Add Drush Services to league/container 3.x
    protected function addDrushServices($container, ClassLoader $loader, DrushDrupalFinder $drupalFinder, SiteAliasManager $aliasManager, DrushConfig $config, OutputInterface $output): void
    {
        // Override Robo's logger with a LoggerManager that delegates to the Drush logger.
        Robo::addShared($container, 'logger', '\Drush\Log\DrushLoggerManager')
          ->addMethodCall('setLogOutputStyler', ['logStyler'])
          ->addMethodCall('add', ['drush', new Logger($output)]);

        Robo::addShared($container, 'loader', $loader);
        Robo::addShared($container, 'site.alias.manager', $aliasManager);

        // Fetch the runtime config, where -D et. al. are stored, and
        // add a reference to it to the container.
        Robo::addShared($container, 'config.runtime', $config->getContext(ConfigOverlay::PROCESS_CONTEXT));

        // Override Robo's formatter manager with our own
        // @todo not sure that we'll use this. Maybe remove it.
        Robo::addShared($container, 'formatterManager', DrushFormatterManager::class)
            ->addMethodCall('addDefaultFormatters', [])
            ->addMethodCall('addDefaultSimplifiers', [])
            ->addMethodCall('addSimplifier', [new EntityToArraySimplifier()]);

        // Add some of our own objects to the container
        Robo::addShared($container, 'service.manager', 'Drush\Runtime\ServiceManager')
            ->addArgument('loader')
            ->addArgument('config')
            ->addArgument('logger');
        Robo::addShared($container, 'bootstrap.drupal8', 'Drush\Boot\DrupalBoot8')
            ->addArgument('service.manager')
            ->addArgument('loader');
        Robo::addShared($container, 'bootstrap.manager', 'Drush\Boot\BootstrapManager')
            ->addMethodCall('setDrupalFinder', [$drupalFinder])
            ->addMethodCall('add', ['bootstrap.drupal8']);
        Robo::addShared($container, 'bootstrap.hook', 'Drush\Boot\BootstrapHook')
          ->addArgument('bootstrap.manager');
        Robo::addShared($container, 'tildeExpansion.hook', 'Drush\Runtime\TildeExpansionHook');
        Robo::addShared($container, 'process.manager', ProcessManager::class)
            ->addMethodCall('setConfig', ['config'])
            ->addMethodCall('setConfigRuntime', ['config.runtime']);
        Robo::addShared($container, 'redispatch.hook', 'Drush\Runtime\RedispatchHook')
            ->addArgument('process.manager');

        // Robo does not manage the command discovery object in the container,
        // but we will register and configure one for our use.
        // TODO: Some old adapter code uses this, but the Symfony dispatcher does not.
        // See Application::commandDiscovery().
        Robo::addShared($container, 'commandDiscovery', 'Consolidation\AnnotatedCommand\CommandFileDiscovery')
            ->addMethodCall('addSearchLocation', ['CommandFiles'])
            ->addMethodCall('setSearchPattern', ['#.*(Commands|CommandFile).php$#']);

        // Error and Shutdown handlers
        Robo::addShared($container, 'errorHandler', 'Drush\Runtime\ErrorHandler');
        Robo::addShared($container, 'shutdownHandler', 'Drush\Runtime\ShutdownHandler');

        // Add inflectors. @see \Drush\Boot\BaseBoot::inflect
        $container->inflector(SiteAliasManagerAwareInterface::class)
            ->invokeMethod('setSiteAliasManager', ['site.alias.manager']);
        $container->inflector(ProcessManagerAwareInterface::class)
            ->invokeMethod('setProcessManager', ['process.manager']);
    }

    protected function alterServicesForDrush($container, Application $application): void
    {
        $paramInjection = $container->get('parameterInjection');
        $paramInjection->register('Symfony\Component\Console\Style\SymfonyStyle', new DrushStyleInjector());

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

        ProcessManager::addTransports($container->get('process.manager'));
    }

    protected function injectApplicationServices($container, Application $application): void
    {
        $application->setLogger($container->get('logger'));
        $application->setBootstrapManager($container->get('bootstrap.manager'));
        $application->setAliasManager($container->get('site.alias.manager'));
        $application->setRedispatchHook($container->get('redispatch.hook'));
        $application->setTildeExpansionHook($container->get('tildeExpansion.hook'));
        $application->setDispatcher($container->get('eventDispatcher'));
        $application->setConfig($container->get('config'));
        $application->setServiceManager($container->get('service.manager'));
    }
}
