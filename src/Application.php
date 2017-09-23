<?php
namespace Drush;

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Drush\Boot\BootstrapManager;
use Drush\Log\LogLevel;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Our application object
 *
 * Note: Implementing *AwareInterface here does NOT automatically cause
 * that corresponding service to be injected into the Application. This
 * is because the application object is created prior to the DI container.
 * See DependencyInjection::injectApplicationServices() to add more services.
 */
class Application extends SymfonyApplication implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var BootstrapManager */
    protected $bootstrapManager;

    /**
     * @param string $name
     * @param string $version
     */
    public function __construct($name, $version)
    {
        parent::__construct($name, $version);

        // TODO: Add all of Drush's global options that are NOT handled
        // by PreflightArgs here.

        //
        // All legacy global options from drush_get_global_options() in drush.inc:
        //
        // Options handled by PreflightArgs:
        //
        //   --root / -r
        //   --include
        //   --config
        //   --alias-path
        //   --local
        //
        // Global options registered with Symfony:
        //
        //   --remote-host
        //   --remote-user
        //   --root / -r
        //   --uri / -l
        //   --simulate
        //   --strict
        //   --debug / -d : equivalent to -vv
        //   --yes / -y : equivalent to --no-interaction
        //   --no / -n : equivalent to --no-interaction
        //
        // Functionality provided by Symfony:
        //
        //   --verbose / -v
        //   --help
        //   --quiet
        //
        // No longer supported
        //
        //   --nocolor           Equivalent to --no-ansi
        //   --search-depth      We could just decide the level we will search for aliases
        //   --show-invoke
        //   --early             Completion handled by standard symfony extension
        //   --complete-debug
        //   --interactive       If command isn't -n, then it is interactive
        //   --command-specific  Now handled by consolidation/config component
        //   --php               If needed prefix command with PATH=/path/to/php:$PATH. Also see #env_vars in site aliases.
        //   --php-options
        //   --pipe
        //
        // Not handled yet (probably to be implemented, but maybe not all):
        //
        //   --tty
        //   --exclude
        //   --backend
        //   --choice
        //   --ignored-modules
        //   --no-label
        //   --label-separator
        //   --cache-default-class
        //   --cache-class-<bin>
        //   --confirm-rollback
        //   --halt-on-error
        //   --deferred-sanitization
        //   --remote-os
        //   --site-list
        //   --reserve-margin
        //   --drush-coverage
        //
        //   --site-aliases
        //   --shell-aliases
        //   --path-aliases
        //   --ssh-options


        $this->getDefinition()
            ->addOption(
                new InputOption('--debug', 'd', InputOption::VALUE_NONE, 'Equivalent to -vv')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--yes', 'y', InputOption::VALUE_NONE, 'Equivalent to --no-interaction.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--remote-host', null, InputOption::VALUE_REQUIRED, 'Run on a remote server.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--remote-user', null, InputOption::VALUE_REQUIRED, 'The user to use in remote execution.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--root', '-r', InputOption::VALUE_REQUIRED, 'The Drupal root for this site.')
            );


        $this->getDefinition()
            ->addOption(
                new InputOption('--uri', '-l', InputOption::VALUE_REQUIRED, 'Which multisite from the selected root to use.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--simulate', null, InputOption::VALUE_NONE, 'Run in simulated mode (show what would have happened).')
            );

        // TODO: Implement handling for 'pipe'
        $this->getDefinition()
            ->addOption(
                new InputOption('--pipe', null, InputOption::VALUE_NONE, 'Select the canonical script-friendly output format.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--define', '-D', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Define a configuration item value.', [])
            );
    }

    public function bootstrapManager()
    {
        return $this->bootstrapManager;
    }

    public function setBootstrapManager(BootstrapManager $bootstrapManager)
    {
        $this->bootstrapManager = $bootstrapManager;
    }

    /**
     * Return the framework uri selected by the user.
     */
    public function getUri()
    {
        if (!$this->bootstrapManager) {
            return 'default';
        }
        return $this->bootstrapManager->getUri();
    }

    /**
     * Set the framework uri selected by the user.
     */
    public function setUri($uri)
    {
        if ($this->bootstrapManager) {
            $this->bootstrapManager->setUri($uri);
        }
    }

    /**
     * @inheritdoc
     */
    public function find($name)
    {
        try {
            return parent::find($name);
        } catch (CommandNotFoundException $e) {
            // If we have no bootstrap manager, then just re-throw
            // the exception.
            if (!$this->bootstrapManager) {
                throw $e;
            }

            // TODO: We could also fail-fast (throw $e) if bootstrapMax made no progress.
            $this->logger->log(LogLevel::DEBUG, 'Bootstrap futher to find {command}', ['command' => $name]);
            $this->bootstrapManager->bootstrapMax();

            // TODO: parent::find resets log level? Log below not printed, but is printed at LogLevel::WARNING.
            $this->logger->log(LogLevel::DEBUG, 'Done with bootstrap max');

            // Try to find it again. This time the exception will
            // not be caught if the command cannot be found.
            return parent::find($name);
        }
    }

    /**
     * @inheritdoc
     *
     * Note: This method is called twice, as we wish to configure the IO
     * objects earlier than Symfony does. We could define a boolean class
     * field to record when this method is called, and do nothing on the
     * second call. At the moment, the work done here is trivial, so we let
     * it happen twice.
     */
    protected function configureIO(InputInterface $input, OutputInterface $output)
    {
        // Do default Symfony confguration.
        parent::configureIO($input, $output);

        // Process legacy Drush global options.
        // Note that `getParameterOption` returns the VALUE of the option if
        // it is found, or NULL if it finds an option with no value.
        if ($input->getParameterOption(['--yes', '-y', '--no', '-n'], false, true) !== false) {
            $input->setInteractive(false);
        }
        // Symfony will set these later, but we want it set upfront
        if ($input->getParameterOption(['--verbose', '-v'], false, true) !== false) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }
        // We are not using "very verbose", but set this for completeness
        if ($input->getParameterOption(['-vv'], false, true) !== false) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
        // Use -vvv of --debug for even more verbose logging.
        if ($input->getParameterOption(['--debug', '-d', '-vvv'], false, true) !== false) {
            $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        }
    }

    /**
     * Configure the application object and register all of the commandfiles
     * available in the search paths provided via Preflight
     */
    public function configureAndRegisterCommands(InputInterface $input, OutputInterface $output, $commandfileSearchpath)
    {
        // Symfony will call this method for us in run() (it will be
        // called again), but we want to call it up-front, here, so that
        // our $input and $output objects have been appropriately
        // configured in case we wish to use them (e.g. for logging) in
        // any of the configuration steps we do here.
        $this->configureIO($input, $output);

        $discovery = $this->commandDiscovery();
        $commandClasses = $discovery->discover($commandfileSearchpath, '\Drush');
        $this->loadCommandClasses($commandClasses);

        // For now: use Symfony's built-in help, as Drush's version
        // assumes we are using the legacy Drush dispatcher.
        unset($commandClasses[__DIR__ . '/Commands/help/HelpCommands.php']);
        unset($commandClasses[__DIR__ . '/Commands/help/ListCommands.php']);

        // Use the robo runner to register commands with Symfony application.
        // This method could / should be refactored in Robo so that we can use
        // it without creating a Runner object that we would not otherwise need.
        $runner = new \Robo\Runner();
        $runner->registerCommandClasses($this, $commandClasses);
    }

    /**
     * Ensure that any discovered class that is not part of the autoloader
     * is, in fact, included.
     */
    protected function loadCommandClasses($commandClasses)
    {
        foreach ($commandClasses as $file => $commandClass) {
            if (!class_exists($commandClass)) {
                include $file;
            }
        }
    }

    /**
     * Create a command file discovery object
     */
    protected function commandDiscovery()
    {
        $discovery = new CommandFileDiscovery();
        $discovery
            ->setIncludeFilesAtBase(true)
            ->setSearchLocations(['Commands', 'Hooks'])
            ->setSearchPattern('#.*(Command|Hook)s?.php$#');
        return $discovery;
    }
}
