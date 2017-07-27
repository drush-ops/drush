<?php
namespace Drush\Boot;

use Composer\Autoload\ClassLoader;
use Drush\Drush;
use Drush\Config\Environment;
use Drush\Config\ConfigLocator;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

use Webmozart\PathUtil\Path;

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
     * @var Environment $environment
     */
    protected $environment;

    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    public function init()
    {
        // Define legacy constants, and include legacy files that Drush still needs
        LegacyPreflight::includeCode($this->environment->drushBasePath());
        LegacyPreflight::defineConstants($this->environment->drushBasePath());

        // Install our termination handlers
        $this->setTerminationHandlers();
    }

    /**
     * Preprocess the args, removing any @sitealias that may be present.
     * Arguments and options not used during preflight will be processed
     * with an ArgvInput.
     */
    public function preflightArgs($argv)
    {
        $argProcessor = new ArgsPreprocessor();
        $preflightArgs = new PreflightArgs();
        $argProcessor->parseArgv($argv, $preflightArgs);

        return $preflightArgs;
    }

    public function prepareConfig($preflightArgs, $environment)
    {
        // Load configuration and aliases from defined global locations
        // where such things are found.
        $configLocator = new ConfigLocator();
        $configLocator->setLocal($preflightArgs->isLocal());
        $configLocator->addUserConfig($preflightArgs->configPath(), $environment->systemConfigPath(), $environment->homeDir());
        $configLocator->addDrushConfig($environment->drushBasePath());
        $configLocator->addAliasConfig($preflightArgs->alias(), $preflightArgs->aliasPath(), $environment->homeDir());

        // Make our environment settings available as configuration items
        $configLocator->addLoader(new EnvironmentConfigLoader($environment));

        return $configLocator;
    }

    public function run($argv)
    {
        // Get the preflight args and begin collecting configuration files.
        $preflightArgs = $this->preflightArgs($argv);
        $configLocator = $this->prepareConfig($preflightArgs, $this->environment);

        // Determine the local Drupal site targeted, if any
        // TODO: We should probably pass cwd into the bootstrap manager as a parameter.
        Drush::bootstrapManager()->locateRoot($preflightArgs->selectedSite());

        // TODO: Include the Composer autoload for Drupal (if different)

        // Extend configuration and alias files to include files in target Drupal site.
        $configLocator->addSiteConfig(Drush::bootstrapManager()->getRoot());

        // Create the Symfony Application et. al.
        $input = new ArgvInput($preflightArgs->args());
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $application = new \Symfony\Component\Console\Application('Drush Commandline Tool', Drush::getVersion());

        // Set up the DI container
        $container = DependencyInjection::initContainer($application, $configLocator->config(), $input, $output);

        // TODO: We still need to add the commandfiles to the application

        // Run the Symfony Application
        // Predispatch: call a remote Drush command if applicable (via a 'pre-init' hook)
        // Bootstrap: bootstrap site to the level requested by the command (via a 'post-init' hook)
        $status = $application->run($input, $output);

        return $status;
    }

    /**
     * Make sure we are notified on exit, and when bad things happen.
     */
    protected function setTerminationHandlers()
    {
        // Set an error handler and a shutdown function
        // TODO: move these to a class somewhere
        set_error_handler('drush_error_handler');
        register_shutdown_function('drush_shutdown');
    }
}
