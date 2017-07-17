<?php
namespace Drush\Boot;

use Composer\Autoload\ClassLoader;
use Webmozart\PathUtil\Path;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prepare to bootstrap Drupal
 *
 * - Determine the site to use
 * - Set up the DI container
 * - Start the bootstrap process
 */
class Preflight
{
    /**
     * @var ClassLoader $loader The class loader returned from autoload.php.
     */
    protected $loader;

    /**
     * @var $vendorPath The path to the 'vendor' directory
     */
    protected $vendorPath;

    public function __construct(ClassLoader $loader, $vendorPath)
    {
        $this->loader = $loader;
        $this->vendorPath = $vendorPath;
    }

    public function run($argv)
    {
        $home = Path::getHomeDirectory();
        $drushBasePath = dirname(dirname(__DIR__));

        // Define legacy constants, and include legacy files that Drush still needs
        $this->includeLegacyCode($drushBasePath);
        $this->defineLegacyConstants($drushBasePath);
        $this->setTerminationHandlers();

        // Preprocess the args, removing any @sitealias that may be present
        $argProcessor = new PreprocessArgs($argv);

        // Load configuration and aliases from defined global locations
        // where such things are found.
        // TODO: Support --local to turn off global configuration locations.
        $configLocater = new ConfigLocater();
        $configLocater->addUserConfig($argProcessor->configPath(), $home);
        $configLocater->addDrushConfig($drushBasePath);
        $configLocater->addAliasConfig($argProcessor->alias(), $argProcessor->aliasPath(), $home);

        // Determine the local Drupal site targeted, if any
        // TODO: We should probably pass cwd into the bootstrap manager as a parameter.
        Drush::bootstrapManager()->locateRoot($argProcessor->selectedSite());

        // Include the Composer autoload for Drupal (if different)

        // Extend configuration and alias files to include files in target Drupal site.
        $configLocater->addSiteConfig(Drush::bootstrapManager()->getRoot());

        // Create the Symfony Application et. al.
        $input = new ArgvInput($argProcessor->args());
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $application = new \Symfony\Component\Console\Application('Drush Commandline Tool', Drush::getVersion());

        // Set up the DI container
        $container = $this->initDependencyInjectionContainer($application, $configLocater->config(), $input, $output);

        // Run the Symfony Application
        // Predispatch: call a remote Drush command if applicable (via a 'pre-init' hook)
        // Bootstrap: bootstrap site to the level requested by the command (via a 'post-init' hook)
        $status = $application->run($input, $output);

        return $status;
    }

    /**
     * Define legacy constants.
     */
    protected function defineLegacyConstants($drushBasePath)
    {
        define('DRUSH_REQUEST_TIME', microtime(TRUE));
        define('DRUSH_BASE_PATH', $drushBasePath);

        /*
         * @deprecated. Use Drush::getVersion().
         */
        define('DRUSH_VERSION', Drush::getVersion());

        /*
         * @deprecated. Use Drush::getMajorVersion().
         */
        define('DRUSH_MAJOR_VERSION', Drush::getMajorVersion());

        /*
         * @deprecated. Use Drush::getMinorVersion().
         */
        define('DRUSH_MINOR_VERSION', Drush::getMinorVersion());

        /*
         * @deprecated. Do not use
         */
        drush_set_context('argc', $GLOBALS['argc']);
        drush_set_context('argv', $GLOBALS['argv']);
        drush_set_context('DRUSH_VENDOR_PATH', $this->vendorPath);
        drush_set_context('DRUSH_CLASSLOADER', $this->loader);
    }

    /**
     * Include old code. It is an aspirational goal to remove or refactor
     * all of this into more modular, class-based code.
     */
    protected function includeLegacyCode($drushBasePath)
    {
        require_once $drushBasePath . '/includes/preflight.inc';
        require_once $drushBasePath . '/includes/bootstrap.inc';
        require_once $drushBasePath . '/includes/environment.inc';
        require_once $drushBasePath . '/includes/annotationcommand_adapter.inc';
        require_once $drushBasePath . '/includes/command.inc';
        require_once $drushBasePath . '/includes/drush.inc';
        require_once $drushBasePath . '/includes/backend.inc';
        require_once $drushBasePath . '/includes/batch.inc';
        require_once $drushBasePath . '/includes/context.inc';
        require_once $drushBasePath . '/includes/sitealias.inc';
        require_once $drushBasePath . '/includes/exec.inc';
        require_once $drushBasePath . '/includes/drupal.inc';
        require_once $drushBasePath . '/includes/output.inc';
        require_once $drushBasePath . '/includes/cache.inc';
        require_once $drushBasePath . '/includes/filesystem.inc';
        require_once $drushBasePath . '/includes/legacy.inc';
    }

    /**
     * Make sure we are notified on exit, and when bad things happen.
     */
    protected function setTerminationHandlers()
    {
        // Set an error handler and a shutdown function
        set_error_handler('drush_error_handler');
        register_shutdown_function('drush_shutdown');
    }

    /**
     * Set up our dependency injection container.
     *
     * The Drupal6 boot service is needed in order to show the D6 deprecation message.
     */
    protected function initDependencyInjectionContainer($application, $config, $input = null, $output = null)
    {
        // Create default input and output objects if they were not provided
        if (!$input) {
            $input = new StringInput('');
        }
        if (!$output) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        }
        // Set up our dependency injection container.
        $container = new \League\Container\Container();

        \Robo\Robo::configureContainer($container, $application, $config, $input, $output);
        $container->add('container', $container);

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

        // Robo does not manage the command discovery object in the container,
        // but we will register and configure one for our use.
        $container->share('commandDiscovery', 'Consolidation\AnnotatedCommand\CommandFileDiscovery')
          ->withMethodCall('addSearchLocation', ['CommandFiles'])
          ->withMethodCall('setSearchPattern', ['#.*(Commands|CommandFile).php$#']);

        // Store the container in the \Drush object
        Drush::setContainer($container);
        \Robo\Robo::setContainer($container);

        // Add our own callback to the hook manager
        $hookManager = $container->get('hookManager');
        $hookManager->addOutputExtractor(new \Drush\Backend\BackendResultSetter());
        // @todo: do we need both backend result setters? The one below should be removed at some point.
        $hookManager->add('annotatedcomand_adapter_backend_result', HookManager::EXTRACT_OUTPUT);

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

        return $container;
    }
}
