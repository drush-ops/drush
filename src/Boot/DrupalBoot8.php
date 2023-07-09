<?php

namespace Drush\Boot;

use Drupal\Core\DrupalKernelInterface;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Database\Database;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Session\AnonymousUserSession;
use Drush\Config\ConfigLocator;
use Drush\Drupal\DrushLoggerServiceProvider;
use Drush\Drupal\DrushServiceModifier;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Robo\Robo;

class DrupalBoot8 extends DrupalBoot implements AutoloaderAwareInterface
{
    use AutoloaderAwareTrait;

    /**
     * @var LoggerInterface
     */
    protected $drupalLoggerAdapter;

    /**
     * @var DrupalKernelInterface
     */
    protected $kernel;

    /**
     * @var Request
     */
    protected $request;

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

    /**
     * Sometimes (e.g. in the integration tests), the DrupalBoot
     * object will be cached, and re-injected into a fresh set
     * of preflight / bootstrap objects. When this happens, the
     * new Drush logger will be injected into the boot object. If
     * this happens after we have created the Drupal logger adapter
     * (i.e., after bootstrapping Drupal), then we also need to
     * update the logger reference in that adapter.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->drupalLoggerAdapter) {
            $this->drupalLoggerAdapter->setLogger($logger);
        }
        parent::setLogger($logger);
    }

    public function validRoot($path)
    {
        if (!empty($path) && is_dir($path) && file_exists($path . '/autoload.php')) {
            // Additional check for the presence of core/composer.json to
            // grant it is not a Drupal 7 site with a base folder named "core".
            $candidate = 'core/includes/common.inc';
            if (file_exists($path . '/' . $candidate) && file_exists($path . '/core/core.services.yml')) {
                if (file_exists($path . '/core/misc/drupal.js') || file_exists($path . '/core/assets/js/drupal.js')) {
                    return $candidate;
                }
            }
        }
    }

    public function getVersion($drupal_root): string
    {
        // Are the class constants available?
        if (!$this->hasAutoloader()) {
            throw new \Exception('Cannot access Drupal class constants - Drupal autoloader not loaded yet.');
        }
        return \Drupal::VERSION;
    }

    /**
     * Beware, this function populates Database::Connection info.
     *
     * See https://github.com/drush-ops/drush/issues/3903.
     * @param bool $require_settings
     * @param bool $reset
     *
     * @return string|void
     */
    public function confPath($require_settings = true, $reset = false)
    {

        if (\Drupal::hasService('kernel')) {
            $site_path = \Drupal::service('kernel')->getSitePath();
        }
        if (!isset($site_path) || empty($site_path)) {
            $site_path = DrupalKernel::findSitePath($this->getRequest(), $require_settings);
        }
        return $site_path;
    }

    public function bootstrapDrupalCore(BootstrapManager $manager, $drupal_root): string
    {
        return Path::join($drupal_root, 'core');
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
     * of the drush drupal site bootstrap.
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
        // D8 omits this bootstrap level as nothing special needs to be done.
        parent::bootstrapDrupalDatabase($manager);
    }

    public function bootstrapDrupalConfiguration(BootstrapManager $manager, AnnotationData $annotationData = null): void
    {
        // Coax \Drupal\Core\DrupalKernel::discoverServiceProviders to add our logger.
        $GLOBALS['conf']['container_service_providers'][] = DrushLoggerServiceProvider::class;

        // Default to the standard kernel.
        $kernel = Kernels::DRUPAL;
        if (!empty($annotationData)) {
            $kernel = $annotationData->get('kernel', Kernels::DRUPAL);
        }
        $classloader = $this->autoloader();
        $request = $this->getRequest();
        $kernel_factory = Kernels::getKernelFactory($kernel);
        $allow_dumping = $kernel !== Kernels::UPDATE;
        /** @var DrupalKernelInterface kernel */
        $this->kernel = $kernel_factory($request, $classloader, 'prod', $allow_dumping, $manager->getRoot());
        // Include Drush services in the container.
        // @see Drush\Drupal\DrupalKernel::addServiceModifier()
        $this->kernel->addServiceModifier(new DrushServiceModifier());

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
        $this->addDrupalModuleDrushCommands($manager);

        // Set a default account to make sure the correct timezone is set
        $this->kernel->getContainer()->get('current_user')->setAccount(new AnonymousUserSession());
    }

    public function addDrupalModuleDrushCommands($manager): void
    {
        $application = Drush::getApplication();
        $runner = Drush::runner();

        // We have to get the service command list from the container, because
        // it is constructed in an indirect way during the container initialization.
        // The upshot is that the list of console commands is not available
        // until after $kernel->boot() is called.
        $container = \Drupal::getContainer();

        // Find the containerless commands, generators and command info alterers
        $bootstrapCommandClasses = $application->bootstrapCommandClasses();
        $commandInfoAlterers = [];
        foreach ($container->getParameter('container.modules') as $moduleId => $moduleInfo) {
            $path = dirname(DRUPAL_ROOT . '/' . $moduleInfo['pathname']) . '/src/Drush/';
            $commandsInThisModule = $this->discoverModuleCommands([$path], "\\Drupal\\" . $moduleId . "\\Drush");
            $bootstrapCommandClasses = array_merge($bootstrapCommandClasses, $commandsInThisModule);
            $commandInfoAlterersInThisModule = $this->discoverCommandInfoAlterers([$path], "\\Drupal\\" . $moduleId . "\\Drush");
            $commandInfoAlterers = array_merge($commandInfoAlterers, $commandInfoAlterersInThisModule);
        }

        // Find the command info alterers in Drush services.
        if ($container->has(DrushServiceModifier::DRUSH_COMMAND_INFO_ALTERER_SERVICES)) {
            $serviceCommandInfoAltererList = $container->get(DrushServiceModifier::DRUSH_COMMAND_INFO_ALTERER_SERVICES);
            $commandFactory = Drush::commandFactory();
            $commandInfoAlterers = array_merge($commandInfoAlterers, $serviceCommandInfoAltererList->getCommandList());
        }

        // Set the command info alterers.
        foreach ($serviceCommandInfoAltererList->getCommandList() as $altererHandler) {
            $commandFactory->addCommandInfoAlterer($altererHandler);
            $this->logger->debug(dt('Commands are potentially altered in !class.', ['!class' => get_class($altererHandler)]));
        }

        // Register the Drush Symfony Console commands found in Drush services
        if ($container->has(DrushServiceModifier::DRUSH_CONSOLE_SERVICES)) {
            $serviceCommandList = $container->get(DrushServiceModifier::DRUSH_CONSOLE_SERVICES);
            foreach ($serviceCommandList->getCommandList() as $command) {
                $manager->inflect($command);
                $this->logger->debug(dt('Add a command: !name', ['!name' => $command->getName()]));
                $application->add($command);
            }
        }

        // Do the same thing with the annotation commands.
        if ($container->has(DrushServiceModifier::DRUSH_COMMAND_SERVICES)) {
            $serviceCommandList = $container->get(DrushServiceModifier::DRUSH_COMMAND_SERVICES);
            foreach ($serviceCommandList->getCommandList() as $commandHandler) {
                $manager->inflect($commandHandler);
                $this->logger->debug(dt('Add a commandfile class: !name', ['!name' => get_class($commandHandler)]));
                $runner->registerCommandClass($application, $commandHandler);
            }
        }

        // Finally, instantiate all of the classes we discovered in
        // configureAndRegisterCommands, and all of the classes we find
        // via 'discoverModuleCommands' that have static create factory methods.
        foreach ($bootstrapCommandClasses as $class) {
            $commandHandler = null;
            try {
                // We insist that the command class have a static 'create' method.
                // We could make this optional, but doing so would run the risk
                // of double-instantiating Drush service commands, if anyone decided
                // to put those in the same namespace (\Drupal\modulename\Drush\Commands)
                if ($this->hasStaticCreateFactory($class)) {
                    $commandHandler = $class::create($container);
                }
            } catch (\Exception $e) {
            }
            // Fail silently if the command handler could not be
            // instantiated, e.g. if it tries to fetch services from
            // a module that has not been enabled.
            if ($commandHandler) {
                $manager->inflect($commandHandler);
                $runner->registerCommandClass($application, $commandHandler);
            }
        }
    }

    protected function hasStaticCreateFactory($class)
    {
        if (!method_exists($class, 'create')) {
            return false;
        }

        $reflectionMethod = new \ReflectionMethod($class, 'create');
        return $reflectionMethod->isStatic();
    }

    /**
     * Discover module commands. This is the preferred way to find module
     * commands in Drush 12+.
     */
    protected function discoverModuleCommands(array $directoryList, string $baseNamespace): array
    {
        $discovery = new CommandFileDiscovery();
        $discovery
            ->setIncludeFilesAtBase(true)
            ->setSearchDepth(1)
            ->ignoreNamespacePart('src')
            ->setSearchLocations(['Commands', 'Hooks', 'Generators'])
            ->setSearchPattern('#.*(Command|Hook|Generator)s?.php$#');
        $baseNamespace = ltrim($baseNamespace, '\\');
        $commandClasses = $discovery->discover($directoryList, $baseNamespace);
        return array_values($commandClasses);
    }

    protected function discoverCommandInfoAlterers(array $directoryList, string $baseNamespace): array
    {
        $discovery = new CommandFileDiscovery();
        $discovery
            ->setIncludeFilesAtBase(true)
            ->setSearchDepth(1)
            ->ignoreNamespacePart('src')
            ->setSearchLocations(['CommandInfoAlterers'])
            ->setSearchPattern('#.*CommandInfoAlterer.php$#');
        $baseNamespace = ltrim($baseNamespace, '\\');
        $commandClasses = $discovery->discover($directoryList, $baseNamespace);
        return array_values($commandClasses);
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
            $this->kernel->terminate($this->getRequest(), $response);
        }
    }
}
