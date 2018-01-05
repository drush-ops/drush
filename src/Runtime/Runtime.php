<?php
namespace Drush\Runtime;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\ValidatorInterface;
use Drush\Drush;
use Drush\Preflight\Preflight;

/**
 * Control the Drush runtime environment
 *
 * - Preflight
 * - Symfony application run
 * - Bootstrap
 * - Command execution
 * - Termination
 */
class Runtime
{
    /** @var Preflight */
    protected $preflight;

    /**
     * Runtime constructor
     *
     * @param Preflight $preflight the prefligth object
     */
    public function __construct(Preflight $preflight)
    {
        $this->preflight = $preflight;
    }

    /**
     * Run the application, catching any errors that may be thrown.
     * Typically, this will happen only for code that fails fast during
     * preflight. Later code should catch and handle its own exceptions.
     */
    public function run($argv)
    {
        try {
            $status = $this->doRun($argv);
        } catch (\Exception $e) {
            $status = $e->getCode();
            $message = $e->getMessage();
            // Uncaught exceptions could happen early, before our logger
            // and other classes are initialized. Print them and exit.
            $this->preflight->logger()->setDebug(true)->log($message);
        }
        return $status;
    }

    /**
     * Start up Drush
     */
    protected function doRun($argv)
    {
        // Do the preflight steps
        $status = $this->preflight->preflight($argv);

        // If preflight signals that we are done, then exit early.
        if ($status !== false) {
            return $status;
        }

        $commandfileSearchpath = $this->preflight->getCommandFilePaths();
        $this->preflight->logger()->log('Commandfile search paths: ' . implode(',', $commandfileSearchpath));
        $this->preflight->config()->set('runtime.commandfile.paths', $commandfileSearchpath);

        // Require the Composer autoloader for Drupal (if different)
        $loader = $this->preflight->loadSiteAutoloader();

        // Create the Symfony Application et. al.
        $input = $this->preflight->createInput();
        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $application = new \Drush\Application('Drush Commandline Tool', Drush::getVersion());

        // Set up the DI container.
        $container = DependencyInjection::initContainer(
            $application,
            $this->preflight->config(),
            $input,
            $output,
            $loader,
            $this->preflight->drupalFinder(),
            $this->preflight->aliasManager()
        );

        // Now that the DI container has been set up, the Application object will
        // have a reference to the bootstrap manager et. al., so we may use it
        // as needed. Tell the application to coordinate between the Bootstrap
        // manager and the alias manager to select a more specific URI, if
        // one was not explicitly provided earlier in the preflight.
        $application->refineUriSelection($this->preflight->environment()->cwd());

        // Our termination handlers depend on classes we set up via DependencyInjection,
        // so we do not want to enable it any earlier than this.
        // TODO: Inject a termination handler into this class, so that we don't
        // need to add these e.g. when testing.
        $this->setTerminationHandlers();

        // Add global options and copy their values into Config.
        $application->configureGlobalOptions();

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
