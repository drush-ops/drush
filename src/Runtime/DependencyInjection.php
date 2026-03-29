<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Psr\Container\ContainerInterface;
use Composer\Autoload\ClassLoader;
use Consolidation\AnnotatedCommand\AnnotatedCommandFactory;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Consolidation\AnnotatedCommand\CommandProcessor;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinHandler;
use Consolidation\AnnotatedCommand\Options\AlterOptionsCommandEvent;
use Consolidation\AnnotatedCommand\Options\PrepareTerminalWidthOption;
use Consolidation\AnnotatedCommand\ParameterInjection;
use Consolidation\Config\ConfigAwareInterface;
use Consolidation\Config\ConfigInterface;
use Consolidation\Config\Inject\ConfigForCommand;
use Consolidation\Config\Util\ConfigOverlay;
use Consolidation\Log\LogOutputStyler;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Drush\Application;
use Drush\Boot\BootstrapHook;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBoot8;
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
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

    public function desiredHandlers(array $handlerList): void
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

        // With league/container, first call wins, so add Drush services first.
        $this->addDrushServices($container, $loader, $drupalFinder, $aliasManager, $config, $output, $input);

        // Register the core annotated-command framework services.
        $this->configureContainer($container, $application, $config, $input, $output);
        $container->add('container', $container);
        $container->add(ContainerInterface::class, 'container'); // For autowiring

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

    /**
     * Register core services in the container.
     *
     * Sets up the annotated-command framework services (hookManager,
     * commandFactory, commandProcessor, eventDispatcher, etc.) and
     * standard inflectors.
     */
    protected function configureContainer(Container $container, Application $application, ConfigInterface $config, InputInterface $input, OutputInterface $output): void
    {
        $config->set(DrushConfig::DECORATED, $output->isDecorated());
        $config->set(DrushConfig::INTERACTIVE, $input->isInteractive());

        $container->addShared('application', $application);
        $container->addShared('config', $config);
        $container->addShared('input', $input);
        $container->addShared('output', $output);

        // Logging support
        $container->addShared('logStyler', LogOutputStyler::class);

        // Annotated command framework services
        $container->addShared('injectConfigEventListener', ConfigForCommand::class)
            ->addArgument('config')
            ->addMethodCall('setApplication', ['application']);
        $container->addShared('alterOptionsCommandEvent', AlterOptionsCommandEvent::class)
            ->addArgument('application');
        $container->addShared('hookManager', HookManager::class)
            ->addMethodCall('addCommandEvent', ['alterOptionsCommandEvent'])
            ->addMethodCall('addCommandEvent', ['injectConfigEventListener']);
        $container->addShared('eventDispatcher', EventDispatcher::class)
            ->addMethodCall('addSubscriber', ['hookManager']);
        $container->addShared('prepareTerminalWidthOption', PrepareTerminalWidthOption::class)
            ->addMethodCall('setApplication', ['application']);
        $container->addShared('parameterInjection', ParameterInjection::class);
        $container->addShared('commandProcessor', CommandProcessor::class)
            ->addArgument('hookManager')
            ->addMethodCall('setFormatterManager', ['formatterManager'])
            ->addMethodCall('addPrepareFormatter', ['prepareTerminalWidthOption'])
            ->addMethodCall('setParameterInjection', ['parameterInjection'])
            ->addMethodCall(
                'setDisplayErrorFunction',
                [
                    function ($output, $message) use ($container) {
                        $logger = $container->get('logger');
                        $logger->error($message);
                    }
                ]
            );
        $container->addShared('stdinHandler', StdinHandler::class);
        $container->addShared('commandFactory', AnnotatedCommandFactory::class)
            ->addMethodCall('setCommandProcessor', ['commandProcessor']);

        // Add inflectors for common *AwareInterface patterns
        $container->inflector(ConfigAwareInterface::class)
            ->invokeMethod('setConfig', ['config']);
        $container->inflector(LoggerAwareInterface::class)
            ->invokeMethod('setLogger', ['logger']);
        $container->inflector(InputAwareInterface::class)
            ->invokeMethod('setInput', ['input']);
        $container->inflector(\Consolidation\AnnotatedCommand\Output\OutputAwareInterface::class)
            ->invokeMethod('setOutput', ['output']);
        $container->inflector(CustomEventAwareInterface::class)
            ->invokeMethod('setHookManager', ['hookManager']);
        $container->inflector(StdinAwareInterface::class)
            ->invokeMethod('setStdinHandler', ['stdinHandler']);

        // Make sure the application is appropriately initialized.
        $application->setAutoExit(false);
    }

    // Add Drush Services to the container
    protected function addDrushServices(Container $container, ClassLoader $loader, DrushDrupalFinder $drupalFinder, SiteAliasManager $aliasManager, DrushConfig $config, OutputInterface $output, InputInterface $input): void
    {
        // Drush's logger: a LoggerManager that delegates to the Drush logger.
        $container->addShared('logger', DrushLoggerManager::class)
            ->addMethodCall('setLogOutputStyler', ['logStyler'])
            ->addMethodCall('add', ['drush', new Logger($output)]);
        $container->addShared(LoggerInterface::class, 'logger');  // For autowiring

        $container->addShared(self::LOADER, $loader);
        $container->addShared(ClassLoader::class, self::LOADER);  // For autowiring
        $container->addShared(self::SITE_ALIAS_MANAGER, $aliasManager);
        $container->addShared(SiteAliasManagerInterface::class, self::SITE_ALIAS_MANAGER);  // For autowiring

        // Fetch the runtime config, where -D et. al. are stored, and
        // add a reference to it to the container.
        $container->addShared('config.runtime', $config->getContext(ConfigOverlay::PROCESS_CONTEXT));

        // Drush's formatter manager
        $container->addShared(self::FORMATTER_MANAGER, DrushFormatterManager::class)
            ->addMethodCall('addDefaultFormatters', [])
            ->addMethodCall('addDefaultSimplifiers', [])
            ->addMethodCall('addSimplifier', [new EntityToArraySimplifier()]);
        $container->addShared(FormatterManager::class, self::FORMATTER_MANAGER);  // For autowiring

        // Add Drush services to the container
        $container->addShared('service.manager', ServiceManager::class)
            ->addArgument(self::LOADER)
            ->addArgument('config')
            ->addArgument('logger');
        $container->addShared('bootstrap.drupal8', DrupalBoot8::class)
            ->addArgument('service.manager')
            ->addArgument(self::LOADER);
        $container->addShared(self::BOOTSTRAP_MANAGER, BootstrapManager::class)
            ->addMethodCall('setDrupalFinder', [$drupalFinder])
            ->addMethodCall('add', ['bootstrap.drupal8']);
        $container->addShared(BootstrapManager::class, self::BOOTSTRAP_MANAGER); // For autowiring
        $container->addShared('bootstrap.hook', BootstrapHook::class)
          ->addArgument(self::BOOTSTRAP_MANAGER);
        $container->addShared('tildeExpansion.hook', TildeExpansionHook::class);
        $container->addShared(self::PROCESS_MANAGER, ProcessManager::class)
            ->addMethodCall('setConfig', ['config'])
            ->addMethodCall('setConfigRuntime', ['config.runtime'])
            ->addMethodCall('setDrupalFinder', [$drupalFinder]);
        $container->addShared(ProcessManager::class, self::PROCESS_MANAGER); // For autowiring
        $container->addShared('redispatch.hook', RedispatchHook::class)
            ->addArgument(self::PROCESS_MANAGER);

        // Command discovery
        $container->addShared('commandDiscovery', CommandFileDiscovery::class)
            ->addMethodCall('addSearchLocation', ['CommandFiles'])
            ->addMethodCall('setSearchPattern', ['#.*(Commands|CommandFile).php$#']);

        // Error and Shutdown handlers
        $container->addShared('errorHandler', ErrorHandler::class);
        $container->addShared('shutdownHandler', ShutdownHandler::class);

        // Add inflectors. @see \Drush\Boot\BaseBoot::inflect
        $container->inflector(SiteAliasManagerAwareInterface::class)
            ->invokeMethod('setSiteAliasManager', [self::SITE_ALIAS_MANAGER]);
        $container->inflector(ProcessManagerAwareInterface::class)
            ->invokeMethod('setProcessManager', [self::PROCESS_MANAGER]);
    }

    protected function alterServicesForDrush(Container $container, Application $application, InputInterface $input, OutputInterface $output): void
    {
        $paramInjection = $container->get('parameterInjection');
        $paramInjection->register(SymfonyStyle::class, new DrushStyleInjector());

        // Autowiring aliases
        $container->addShared(EventDispatcherInterface::class, 'eventDispatcher');
        $container->addShared(DrushConfig::class, 'config');

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
