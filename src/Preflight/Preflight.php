<?php
namespace Drush\Preflight;

use Composer\Autoload\ClassLoader;
use Drush\Drush;
use Drush\Config\Environment;
use Drush\Config\ConfigLocator;
use Drush\Config\EnvironmentConfigLoader;
use Drush\SiteAlias\SiteAliasManager;
use DrupalFinder\DrupalFinder;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
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

    /**
     * @var PreflightVerify
     */
    protected $verify;

    /**
     * @var ConfigLocator
     */
    protected $configLocator;

    /**
     * @var DrupalFinder
     */
    protected $drupalFinder;

    public function __construct(Environment $environment, $verify = null, $configLocator = null)
    {
        $this->environment = $environment;
        $this->verify = $verify ?: new PreflightVerify();
        $this->configLocator = $configLocator ?: new ConfigLocator();
        $this->drupalFinder = new DrupalFinder();
    }

    public function init(PreflightArgs $preflightArgs)
    {
        // Define legacy constants, and include legacy files that Drush still needs
        LegacyPreflight::includeCode($this->environment->drushBasePath());
        LegacyPreflight::defineConstants($this->environment, $preflightArgs->applicationPath());
        LegacyPreflight::setContexts($this->environment);
    }

    /**
     * Remapping table for arguments. Anything found in a key
     * here will be converted to the corresponding value entry.
     *
     * For example:
     *    --ssh-options='-i mysite_dsa'
     * will become:
     *    -Dssh.options='-i mysite_dsa'
     *
     * TODO: We could consider loading this from a file or some other
     * source. However, this table is needed very early -- even earlier
     * than config is loaded (since this is needed for preflighting the
     * arguments, which can select config files to load). Hardcoding
     * is probably best; we might want to move to another class, perhaps.
     * We also need this prior to Dependency Injection, though.
     *
     * Eventually, we might want to expose this table to some form of
     * 'help' output, so folks can see the available conversions.
     */
    protected function remapArguments()
    {
        return [
            '--ssh-options' => '-Dssh.options',
            '--php' => '-Druntime.php.path',
            '--php-options' => '-Druntime.php.options',
            '--php-notices' => '-Druntime.php.notices',
            '--halt-on-error' => '-Druntime.php.halt-on-error',
            '--output_charset' => '-Dio.output.charset',
            '--output-charset' => '-Dio.output.charset',
            '--db-su' => '-Dsql.db-su',
            // Map command aliases which Console complains about.
            'si' => 'site-install',
            'en' => 'pm-enable',
        ];
    }

    /**
     * Removal table for arguments. Anythign found here will be silently
     * removed. The option value is ignored; ergo, both --strict and
     * --strict=0 will be removed; however, --stricter will not be removed.
     */
    protected function removeArguments()
    {
        // Now we are going to support rather than remove --strict.
        return [];
    }

    /**
     * Preprocess the args, removing any @sitealias that may be present.
     * Arguments and options not used during preflight will be processed
     * with an ArgvInput.
     */
    public function preflightArgs($argv)
    {
        $argProcessor = new ArgsPreprocessor();
        $remapper = new ArgsRemapper($this->remapArguments(), $this->removeArguments());
        $preflightArgs = new PreflightArgs([]);
        $argProcessor->setArgsRemapper($remapper);

        $argProcessor->parse($argv, $preflightArgs);

        return $preflightArgs;
    }

    public function prepareConfig(PreflightArgs $preflightArgs, Environment $environment)
    {
        // Load configuration and aliases from defined global locations
        // where such things are found.
        $configLocator = new ConfigLocator();
        $configLocator->setLocal($preflightArgs->isLocal());
        $configLocator->addUserConfig($preflightArgs->configPath(), $environment->systemConfigPath(), $environment->userConfigPath());
        $configLocator->addDrushConfig($environment->drushBasePath());

        // Make our environment settings available as configuration items
        $configLocator->addEnvironment($environment);

        return $configLocator;
    }

    /**
     * Start code coverage collection
     *
     * @param PreflightArgs $preflightArgs
     */
    public function startCoverage(PreflightArgs $preflightArgs)
    {
        if ($coverage_file = $preflightArgs->coverageFile()) {
            // TODO: modernize code coverage handling
            drush_set_context('DRUSH_CODE_COVERAGE', $coverage_file);
            xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
            register_shutdown_function('drush_coverage_shutdown');
        }
    }

    /**
     * Run the application, catching any errors that may be thrown.
     * Typically, this will happen only for code that fails fast during
     * preflight. Later code should catch and handle its own exceptions.
     */
    public function run($argv)
    {
        $status = 0;
        try {
            $status = $this->doRun($argv);
        } catch (\Exception $e) {
            $status = $e->getCode();
            $message = $e->getMessage();
            // Uncaught exceptions could happen early, before our logger
            // and other classes are initialized. Print them and exit.
            fwrite(STDERR, "$message\n");
        }
        return $status;
    }

    protected function doRun($argv)
    {
        // Fail fast if there is anything in our environment that does not check out
        $this->verify->verify($this->environment);

        // Get the preflight args and begin collecting configuration files.
        $preflightArgs = $this->preflightArgs($argv);
        $configLocator = $this->prepareConfig($preflightArgs, $this->environment);

        // Do legacy initialization (load static includes, define old constants, etc.)
        $this->init($preflightArgs);

        // Start code coverage
        $this->startCoverage($preflightArgs);

        // TODO: Should we allow config to set values defined by preflightArgs?
        // (e.g. --root and --uri).
        // Maybe preflight args should be one of the config layers, and we
        // should fetch 'root' et. al. from config rather than preflight args.
        $config = $configLocator->config();

        // Copy items from the preflight args into configuration
        $preflightArgs->applyToConfig($config);

        // Determine the local site targeted, if any.
        // Extend configuration and alias files to include files in
        // target site.
        $root = $this->findSelectedSite($preflightArgs);
        $configLocator->addSitewideConfig($root);
        $configLocator->setComposerRoot($this->selectedComposerRoot());

        $paths = $configLocator->getSiteAliasPaths($preflightArgs, $this->environment);

        // Configure alias manager.
        $aliasManager = (new SiteAliasManager())->addSearchLocations($paths);
        $selfAliasRecord = $aliasManager->findSelf($preflightArgs->alias(), $root, $preflightArgs->uri());
        $aliasConfig = $selfAliasRecord->exportConfig();
        $configLocator->addAliasConfig($aliasConfig);

        // Process the selected alias. This might change the selected site,
        // so we will add new site-wide config location for the new root.
        $root = $this->setSelectedSite($selfAliasRecord->localRoot());
        $configLocator->addSitewideConfig($root);

        // Remember the paths to all the files we loaded, so that we can
        // report on it from Drush status or wherever else it may be needed.

        $config->set('runtime.config.paths', $configLocator->configFilePaths());

        // We need to check the php minimum version again, in case anyone
        // has set it to something higher in one of the config files we loaded.
        $this->verify->confirmPhpVersion($config->get('drush.php.minimum-version'));

        // Find all of the available commandfiles, save for those that are
        // provided by modules in the selected site; those will be added
        // during bootstrap.
        $commandfileSearchpath = $this->findCommandFileSearchPath($preflightArgs, $root);

        // Require the Composer autoloader for Drupal (if different)
        $loader = $this->environment->loadSiteAutoloader($root);

        // Create the Symfony Application et. al.
        $input = $preflightArgs->createInput();
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $application = new \Drush\Application('Drush Commandline Tool', Drush::getVersion());

        // Set up the DI container.
        $container = DependencyInjection::initContainer(
            $application,
            $config,
            $input,
            $output,
            $loader,
            $this->drupalFinder,
            $aliasManager
        );

        // Now that the DI container has been set up, the Application object will
        // have a reference to the bootstrap manager et. al., so we may use it
        // as needed. Tell the application to coordinate between the Bootstrap
        // manager and the alias manager to select a more specific URI, if
        // one was not explicitly provided earlier in the preflight.
        $application->refineUriSelection($this->environment->cwd());

        // Our termination handlers depend on classes we set up via DependencyInjection,
        // so we do not want to enable it any earlier than this.
        // TODO: Inject a termination handler into this class, so that we don't
        // need to add these e.g. when testing.
        $this->setTerminationHandlers();

        // Configure the application object and register all of the commandfiles
        // from the search paths we found above.  After this point, the input
        // and output objects are ready & we can start using the logger, etc.
        $application->configureAndRegisterCommands($input, $output, $commandfileSearchpath);

        // Run the Symfony Application
        // Predispatch: call a remote Drush command if applicable (via a 'pre-init' hook)
        // Bootstrap: bootstrap site to the level requested by the command (via a 'post-init' hook)
        $status = $application->run($input, $output);

        // Placate the Drush shutdown handler.
        // TODO: use a more modern termination management strategy
        drush_set_context('DRUSH_EXECUTION_COMPLETED', true);

        // For backwards compatibility (backend invoke needs this in drush_backend_output())
        drush_set_context('DRUSH_ERROR_CODE', $status);

        return $status;
    }

    /**
     * Find the site the user selected based on --root or cwd. If neither of
     * those result in a site, then we will fall back to the vendor path.
     */
    protected function findSelectedSite(PreflightArgs $preflightArgs)
    {
        // TODO: If we want to support ONLY site-local Drush (which is
        // DIFFERENT than --local), then skip the call to `$preflightArgs->selectedSite`
        // and just assign `false` to $selectedRoot.
        $selectedRoot = $preflightArgs->selectedSite($this->environment->cwd());
        return $this->setSelectedSite($selectedRoot, $this->environment->vendorPath());
    }

    protected function setSelectedSite($selectedRoot, $fallbackPath = false)
    {
        $foundRoot = $this->drupalFinder->locateRoot($selectedRoot);
        if (!$foundRoot && $fallbackPath) {
            $this->drupalFinder->locateRoot($fallbackPath);
        }
        return $this->selectedDrupalRoot();
    }

    protected function selectedDrupalRoot()
    {
        return $this->drupalFinder->getDrupalRoot();
    }

    protected function selectedComposerRoot()
    {
        return $this->drupalFinder->getComposerRoot();
    }

    /**
     * Return the search path containing all of the locations where Drush
     * commands are found.
     */
    protected function findCommandFileSearchPath(PreflightArgs $preflightArgs, $root = '')
    {
        // Start with the built-in commands.
        $searchpath = [
            dirname(__DIR__),
        ];

        // Commands specified by 'include' option
        $commandPath = $preflightArgs->commandPath();
        if (is_dir($commandPath)) {
            $searchpath[] = $commandPath;
        }

        if (!$preflightArgs->isLocal()) {
            // System commands, residing in $SHARE_PREFIX/share/drush/commands
            $share_path = $this->environment->systemCommandFilePath();
            if (is_dir($share_path)) {
                $searchpath[] = $share_path;
            }

            // User commands, residing in ~/.drush
            $per_user_config_dir = $this->environment->userConfigPath();
            if (is_dir($per_user_config_dir)) {
                $searchpath[] = $per_user_config_dir;
            }
        }

        $siteCommands = "$root/drupal";
        if (!empty($root) && is_dir($siteCommands)) {
            $searchpath[] = $siteCommands;
        }

        return $searchpath;
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
