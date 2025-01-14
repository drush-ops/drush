<?php

declare(strict_types=1);

namespace Drush\Boot;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Session\AnonymousUserSession;
use Drush\Config\ConfigLocator;
use Drush\Drupal\DrushLoggerServiceProvider;
use Drush\Drupal\Migrate\MigrateRunnerServiceProvider;
use Drush\Drush;
use Drush\Runtime\LegacyServiceFinder;
use Drush\Runtime\LegacyServiceInstantiator;
use Drush\Runtime\ServiceManager;
use Robo\Robo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\TerminableInterface;

class DrupalBoot8 extends DrupalBoot
{
    protected ?DrupalKernelInterface $kernel = null;
    protected Request $request;

    public function __construct(protected ServiceManager $serviceManager, protected $autoloader)
    {
        parent::__construct();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getKernel(): DrupalKernelInterface
    {
        return $this->kernel;
    }

    public function validRoot(?string $path): bool
    {
        if (!empty($path) && is_dir($path) && file_exists($path . '/autoload.php')) {
            // Additional check for the presence of core/composer.json to
            // grant it is not a Drupal 7 site with a base folder named "core".
            $candidate = 'core/includes/common.inc';
            if (file_exists($path . '/' . $candidate) && file_exists($path . '/core/core.services.yml')) {
                if (file_exists($path . '/core/misc/drupal.js') || file_exists($path . '/core/assets/js/drupal.js')) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getVersion($root): string
    {
        return \Drupal::VERSION;
    }

    /**
     * Beware, this function populates Database::Connection info.
     *
     * See https://github.com/drush-ops/drush/issues/3903.
     */
    public function confPath(bool $require_settings = true, bool $reset = false): ?string
    {

        if (\Drupal::hasService('kernel')) {
            $site_path = \Drupal::service('kernel')->getSitePath();
        }
        if (!isset($site_path) || empty($site_path)) {
            $site_path = DrupalKernel::findSitePath($this->getRequest(), $require_settings);
        }
        return $site_path;
    }

    public function bootstrapDrupalSiteValidate(BootstrapManager $manager): bool
    {
        parent::bootstrapDrupalSiteValidate($manager);

        // Normalize URI.
        $uri = rtrim($this->uri, '/') . '/';

        $parsed_url = parse_url($uri);

        // Account for users who omit the http:// prefix.
        if (empty($parsed_url['scheme'])) {
            $this->uri = 'http://' . $this->uri;
            $uri = 'http://' . $uri;
            $parsed_url = parse_url($uri);
        }

        $server = [
            'SCRIPT_FILENAME' => getcwd() . '/index.php',
            'SCRIPT_NAME' => isset($parsed_url['path']) ? $parsed_url['path'] . 'index.php' : '/index.php',
        ] + $_SERVER;
        // To do: split into Drupal 9 and Drupal 10 bootstrap
        if (method_exists(Request::class, 'create')) {
            // Drupal 9
            $request = Request::create($uri, 'GET', [], [], [], $server);
        } else {
            // Drupal 10
            $request = Request::createFromGlobals();
        }
        $request->overrideGlobals();
        $this->setRequest($request);
        return true;
    }

    /**
     * Called by bootstrapDrupalSite to do the main work
     * of the Drush drupal site bootstrap.
     */
    public function bootstrapDoDrupalSite(BootstrapManager $manager): void
    {
        $siteConfig = $this->confPath() . '/drush.yml';

        if (ConfigLocator::addSiteSpecificConfig(Drush::config(), $siteConfig)) {
            $this->logger->debug(dt("Loaded Drush config file at !file.", ['!file' => $siteConfig]));
        } else {
            $this->logger->debug(dt("Could not find a Drush config file at !file.", ['!file' => $siteConfig]));
        }

        // Note: this reports the 'default' site during site:install even if we eventually install to a different multisite.
        $this->logger->info(dt("Initialized Drupal site !site at !site_root", ['!site' => $this->getRequest()->getHttpHost(), '!site_root' => $this->confPath()]));
    }

    public function bootstrapDrupalConfigurationValidate(BootstrapManager $manager): bool
    {
        $conf_file = $this->confPath() . '/settings.php';
        if (!file_exists($conf_file)) {
            $msg = dt("Could not find a Drupal settings.php file at !file.", ['!file' => $conf_file]);
            $this->logger->debug($msg);
            // Cant do this because site:install deliberately bootstraps to configure without a settings.php file.
            // return drush_set_error($msg);
        }
        return true;
    }

    public function bootstrapDrupalDatabaseValidate(BootstrapManager $manager): bool
    {
        // Drupal requires PDO, and Drush requires php 5.6+ which ships with PDO
        // but PHP may be compiled with --disable-pdo.
        if (!class_exists('\PDO')) {
            $this->logger->info(dt('PDO support is required.'));
            return false;
        }

        try {
            // @todo Log queries in addition to logging failure messages?
            $connection = Database::getConnection();
            $connection_options = $connection->getConnectionOptions();
            $connection->open($connection_options);
        } catch (\Exception $e) {
            $this->logger->info('Unable to connect to database with message: ' . $e->getMessage() . '. More debug information is available by running `drush status`. This may occur when Drush is trying to bootstrap a site that has not been installed or does not have a configured database. In this case you can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line. See `drush topic docs-aliases` for details.');
            return false;
        }
        if (!$connection->schema()->tableExists('key_value')) {
            $this->logger->info('key_value table not found. Database may be empty.');
            return false;
        }
        return true;
    }

    public function bootstrapDrupalDatabase(BootstrapManager $manager): void
    {
        // Nothing special needs to be done.
        parent::bootstrapDrupalDatabase($manager);
    }

    public function bootstrapDrupalConfiguration(BootstrapManager $manager, ?AnnotationData $annotationData = null): void
    {
        // Coax \Drupal\Core\DrupalKernel::discoverServiceProviders to add our logger.
        $GLOBALS['conf']['container_service_providers'][] = DrushLoggerServiceProvider::class;
        // Implement a hook in behalf of 'system' module until #2952291 lands.
        // @see https://www.drupal.org/project/drupal/issues/2952291
        $GLOBALS['conf']['container_service_providers'][] = MigrateRunnerServiceProvider::class;

        // Default to the standard kernel.
        $kernel = Kernels::DRUPAL;
        if (!empty($annotationData)) {
            $kernel = $annotationData->get('kernel', Kernels::DRUPAL);
        }
        $request = $this->getRequest();
        $kernel_factory = Kernels::getKernelFactory($kernel);
        $allow_dumping = $kernel !== Kernels::UPDATE;
        $this->kernel = $kernel_factory($request, $this->autoloader, 'prod', $allow_dumping, $manager->getRoot());

        // Unset drupal error handler and restore Drush's one.
        restore_error_handler();

        // Disable automated cron if the module is enabled.
        $GLOBALS['config']['automated_cron.settings']['interval'] = 0;

        parent::bootstrapDrupalConfiguration($manager);
    }

    public function bootstrapDrupalFull(BootstrapManager $manager): void
    {
        $this->logger->debug(dt('Start bootstrap of the Drupal Kernel.'));
        $this->kernel->boot();
        $this->kernel->preHandle($this->getRequest());
        $this->logger->debug(dt('Finished bootstrap of the Drupal Kernel.'));

        parent::bootstrapDrupalFull($manager);

        // Directly add the Drupal core bootstrapped commands.
        Drush::getApplication()->addCommands($this->serviceManager->instantiateDrupalCoreBootstrappedCommands());

        $this->addDrupalModuleDrushCommands($manager);

        // Set a default account to make sure the correct timezone is set
        $this->kernel->getContainer()->get('current_user')->setAccount(new AnonymousUserSession());
    }

    public function addDrupalModuleDrushCommands(BootstrapManager $manager): void
    {
        $application = Drush::getApplication();
        $drushContainer = Drush::getContainer();

        $this->logger->debug(dt("Loading drupal module drush commands & etc.", []));

        // We have to get the service command list from the container, because
        // it is constructed in an indirect way during the container initialization.
        // The upshot is that the list of console commands is not available
        // until after $kernel->boot() is called.
        $container = \Drupal::getContainer();
        $moduleHandler = \Drupal::moduleHandler();

        // Legacy service adapters for drush.services.yml files.
        $serviceFinder = new LegacyServiceFinder($moduleHandler, Drush::config());
        $drushServiceFiles = $serviceFinder->getDrushServiceFiles();
        $legacyServiceInstantiator = new LegacyServiceInstantiator($container, $this->logger);
        $legacyServiceInstantiator->loadServiceFiles($drushServiceFiles);

        // Find the containerless commands, and command info alterers
        $bootstrapCommandClasses = $this->serviceManager->bootstrapCommandClasses();
        $commandInfoAlterers = [];
        foreach ($moduleHandler->getModuleList() as $moduleId => $extension) {
            $path = DRUPAL_ROOT . '/' . $extension->getPath() . '/src/Drush/';
            $commandsInThisModule = $this->serviceManager->discoverModuleCommands([$path], "\\Drupal\\" . $moduleId . "\\Drush");
            // TODO: Maybe $bootstrapCommandClasses could use a better name.
            // These are commandhandlers that have static create factory methods.
            $bootstrapCommandClasses = array_merge($bootstrapCommandClasses, $commandsInThisModule);
            // TODO: Support PSR-4 command info alterers, like bootstrapCommandClasses?
            $commandInfoAlterersInThisModule = $this->serviceManager->discoverModuleCommandInfoAlterers([$path], "\\Drupal\\" . $moduleId . "\\Drush");
            $commandInfoAlterers = array_merge($commandInfoAlterers, $commandInfoAlterersInThisModule);
        }

        // Find the command info alterers in Drush services.
        $commandFactory = Drush::commandFactory();
        $commandInfoAltererInstances = $this->serviceManager->instantiateServices($commandInfoAlterers, $drushContainer, $container);
        $commandInfoAlterers = array_merge($commandInfoAltererInstances, $legacyServiceInstantiator->taggedServices('drush.command_info_alterer'));

        // Set the command info alterers. We must do this prior to calling
        // Robo::register to add any commands, as that is the point where the
        // alteration will happen.
        foreach ($commandInfoAlterers as $altererHandler) {
            $commandFactory->addCommandInfoAlterer($altererHandler);
            $this->logger->debug(dt('Commands are potentially altered in !class.', ['!class' => get_class($altererHandler)]));
        }

        // Register the Drush Symfony Console commands found in Drush services
        $drushServicesConsoleCommands = $legacyServiceInstantiator->taggedServices('console.command');
        foreach ($drushServicesConsoleCommands as $command) {
            $this->serviceManager->inflect($drushContainer, $command);
            $this->logger->debug(dt('Add a command: !name', ['!name' => $command->getName()]));
            $application->add($command);
        }

        // Add annotation commands from drush.services.yml
        $drushServicesCommandHandlers = $legacyServiceInstantiator->taggedServices('drush.command');
        foreach ($drushServicesCommandHandlers as $commandHandler) {
            $this->serviceManager->inflect($drushContainer, $commandHandler);
            $this->logger->debug(dt('Add a commandfile class: !name', ['!name' => get_class($commandHandler)]));
            Robo::register($application, $commandHandler);
        }

        // Instantiate all of the classes we discovered in
        // configureAndRegisterCommands, and all of the classes we find
        // via 'discoverModuleCommands' that have static create factory methods.
        $commandHandlers = $this->serviceManager->instantiateServices($bootstrapCommandClasses, $drushContainer, $container);

        // Inflect and register all command handlers
        foreach ($commandHandlers as $commandHandler) {
            Robo::register($application, $commandHandler);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(): void
    {
        parent::terminate();

        if ($this->kernel) {
            if (method_exists(Response::class, 'create')) {
                $response = Response::create('');
            } else {
                $response = new HtmlResponse();
            }
            assert($this->kernel instanceof TerminableInterface);
            $this->kernel->terminate($this->getRequest(), $response);
        }
    }

    /**
     * Initialize a site on the Drupal root.
     *
     * We now set various contexts that we determined and confirmed to be valid.
     * Additionally we load an optional drush.yml file in the site directory.
     */
    public function bootstrapDrupalSite(BootstrapManager $manager)
    {
        $this->bootstrapDoDrupalSite($manager);
    }
}
