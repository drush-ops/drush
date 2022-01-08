<?php

namespace Drush\Runtime;

use Symfony\Component\Console\Output\ConsoleOutput;
use Drush\Application;
use Drush\Commands\DrushCommands;
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

    /** @var DependencyInjection */
    protected $di;

    const DRUSH_RUNTIME_COMPLETED_NAMESPACE = 'runtime.execution.completed';
    const DRUSH_RUNTIME_EXIT_CODE_NAMESPACE = 'runtime.exit_code';

    /**
     * Runtime constructor
     *
     * @param Preflight $preflight the preflight object
     */
    public function __construct(Preflight $preflight, DependencyInjection $di)
    {
        $this->preflight = $preflight;
        $this->di = $di;
    }

    /**
     * Run the application, catching any errors that may be thrown.
     * Typically, this will happen only for code that fails fast during
     * preflight. Later code should catch and handle its own exceptions.
     */
    public function run($argv)
    {
        try {
            $output = new ConsoleOutput();
            $status = $this->doRun($argv, $output);
        } catch (\Exception $e) {
            // Fallback to status 1 if the Exception has not indicated otherwise.
            $status = $e->getCode() ?: DrushCommands::EXIT_FAILURE;
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
    protected function doRun($argv, $output)
    {
        // Do the preflight steps
        $status = $this->preflight->preflight($argv);

        // If preflight signals that we are done, then exit early.
        if ($status) {
            return $status;
        }

        $commandfileSearchpath = $this->preflight->getCommandFilePaths();
        $this->preflight->logger()->log('Commandfile search paths: ' . implode(',', $commandfileSearchpath));
        $this->preflight->config()->set('runtime.commandfile.paths', $commandfileSearchpath);

        // Require the Composer autoloader for Drupal (if different)
        $loader = $this->preflight->loadSiteAutoloader();

        // Create the Symfony Application et. al.
        $input = $this->preflight->createInput();
        $application = new Application('Drush Commandline Tool', Drush::getVersion());

        // Set up the DI container.
        $container = $this->di->initContainer(
            $application,
            $this->preflight->config(),
            $input,
            $output,
            $loader,
            $this->preflight->drupalFinder(),
            $this->preflight->aliasManager()
        );

        // Our termination handlers are set up via dependency injection,
        // as they require classes that are set up in the DI container.
        // We therefore cannot configure them any earlier than this.
        $this->di->installHandlers($container);

        // Now that the DI container has been set up, the Application object will
        // have a reference to the bootstrap manager et. al., so we may use it
        // as needed. Tell the application to coordinate between the Bootstrap
        // manager and the alias manager to select a more specific URI, if
        // one was not explicitly provided earlier in the preflight.
        $application->refineUriSelection($this->preflight->environment()->cwd());

        // Add global options and copy their values into Config.
        $application->configureGlobalOptions();

        // Configure the application object and register all of the commandfiles
        // from the search paths we found above.  After this point, the input
        // and output objects are ready & we can start using the logger, etc.
        $application->configureAndRegisterCommands($input, $output, $commandfileSearchpath, $loader);

        // Run the Symfony Application
        // Predispatch: call a remote Drush command if applicable (via a 'pre-init' hook)
        // Bootstrap: bootstrap site to the level requested by the command (via a 'post-init' hook)
        $status = $application->run($input, $output);

        // Placate the Drush shutdown handler.
        Runtime::setCompleted();
        Runtime::setExitCode($status);

        return $status;
    }

    /**
     * Mark the current request as having completed successfully.
     */
    public static function setCompleted(): void
    {
        Drush::config()->set(self::DRUSH_RUNTIME_COMPLETED_NAMESPACE, true);
    }

    /**
     * @deprecated
     *   Used by backend.inc
     *
     * Mark the exit code for current request.
     * @param int $code
     */
    public static function setExitCode(int $code): void
    {
        Drush::config()->set(self::DRUSH_RUNTIME_EXIT_CODE_NAMESPACE, $code);
    }

    /**
     * @deprecated
     *   Used by backend.inc
     *
     * Get the exit code for current request.
     */
    public static function exitCode()
    {
        return Drush::config()->get(self::DRUSH_RUNTIME_EXIT_CODE_NAMESPACE, DRUSH_SUCCESS);
    }
}
