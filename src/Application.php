<?php

declare(strict_types=1);

namespace Drush;

use Composer\Autoload\ClassLoader;
use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\SiteAlias\SiteAliasManager;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\RemoteCommandProxy;
use Drush\Config\ConfigAwareTrait;
use Drush\Runtime\RedispatchHook;
use Drush\Runtime\TildeExpansionHook;
use Drush\Runtime\ServiceManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Robo\Robo;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Our application object
 *
 * Note: Implementing *AwareInterface here does NOT automatically cause
 * that corresponding service to be injected into the Application. This
 * is because the application object is created prior to the DI container.
 * See DependencyInjection::injectApplicationServices() to add more services.
 */
class Application extends SymfonyApplication implements LoggerAwareInterface, ConfigAwareInterface
{
    use LoggerAwareTrait;
    use ConfigAwareTrait;

    /** @var BootstrapManager */
    protected $bootstrapManager;

    /** @var SiteAliasManager */
    protected $aliasManager;

    /** @var RedispatchHook */
    protected $redispatchHook;

    /** @var TildeExpansionHook */
    protected $tildeExpansionHook;

    /** @var ServiceManager */
    protected $serviceManager;

    /**
     * Add global options to the Application and their default values to Config.
     */
    public function configureGlobalOptions()
    {
        // Symfony 6.1+ has a --debug option for its completion command.
        if ($this->getDefinition()->hasOption('--debug')) {
            $this->getDefinition()
                ->addOption(
                    new InputOption('--debug', 'd', InputOption::VALUE_NONE, 'Equivalent to -vv')
                );
        }

        $this->getDefinition()
            ->addOption(
                new InputOption('--yes', 'y', InputOption::VALUE_NONE, 'Auto-accept the default for all user prompts. Equivalent to --no-interaction.')
            );

        // Note that -n belongs to Symfony Console's --no-interaction.
        $this->getDefinition()
            ->addOption(
                new InputOption('--no', null, InputOption::VALUE_NONE, 'Cancels at any confirmation prompt.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--root', '-r', InputOption::VALUE_REQUIRED, 'The Drupal root for this site.')
            );


        $this->getDefinition()
            ->addOption(
                new InputOption('--uri', '-l', InputOption::VALUE_REQUIRED, 'A base URL for building links and selecting a multi-site. Defaults to <info>https://default</info>.')
            );

        $this->getDefinition()
            ->addOption(
                new InputOption('--simulate', null, InputOption::VALUE_NONE, 'Run in simulated mode (show what would have happened).')
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

    public function aliasManager()
    {
        return $this->aliasManager;
    }

    public function setAliasManager($aliasManager)
    {
        $this->aliasManager = $aliasManager;
    }

    public function setRedispatchHook(RedispatchHook $redispatchHook)
    {
        $this->redispatchHook = $redispatchHook;
    }

    public function setTildeExpansionHook(TildeExpansionHook $tildeExpansionHook)
    {
        $this->tildeExpansionHook = $tildeExpansionHook;
    }

    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
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
     * If the user did not explicitly select a site URI,
     * then pick an appropriate site from the cwd.
     */
    public function refineUriSelection($cwd)
    {
        if (!$this->bootstrapManager || !$this->aliasManager) {
            return;
        }
        $selfSiteAlias = $this->aliasManager->getSelf();
        if (!$selfSiteAlias->hasRoot() && !$this->bootstrapManager()->drupalFinder()->getDrupalRoot()) {
            return;
        }
        $uri = $selfSiteAlias->uri();

        if (empty($uri)) {
            $uri = $this->selectUri($cwd);
            $selfSiteAlias->setUri($uri);
            $this->aliasManager->setSelf($selfSiteAlias);
        }
        // Update the uri in the bootstrap manager
        $this->bootstrapManager->setUri($uri);
    }

    /**
     * Select a URI to use for the site, based on directory or config.
     */
    public function selectUri($cwd)
    {
        $uri = $this->config->get('options.uri');
        if ($uri) {
            return $uri;
        }
        return $this->bootstrapManager()->selectUri($cwd);
    }

    /**
     * @inheritdoc
     */
    public function find($name)
    {
        if (empty($name)) {
            return;
        }
        $command = $this->bootstrapAndFind($name);
        // Avoid exception when help is being built by https://github.com/bamarni/symfony-console-autocomplete.
        // @todo Find a cleaner solution.
        $argv = Drush::config()->get('runtime.argv');
        if (count($argv) > 1 && $argv[1] !== 'help') {
            $this->checkObsolete($command);
        }
        return $command;
    }

    /**
     * Look up a command. Bootstrap further if necessary.
     */
    protected function bootstrapAndFind($name)
    {
        try {
            return parent::find($name);
        } catch (CommandNotFoundException $e) {
            // Is the unknown command destined for a remote site?
            if ($this->aliasManager) {
                $selfAlias = $this->aliasManager->getSelf();
                if (!$selfAlias->isLocal()) {
                    $command = new RemoteCommandProxy($name, $this->redispatchHook);
                    $command->setApplication($this);
                    return $command;
                }
            }
            // If we have no bootstrap manager, then just re-throw
            // the exception.
            if (!$this->bootstrapManager) {
                throw $e;
            }

            $this->logger->debug('Bootstrap further to find {command}', ['command' => $name]);
            $this->bootstrapManager->bootstrapMax();
            $this->logger->debug('Done with bootstrap max in Application::bootstrapAndFind(): trying to find {command} again.', ['command' => $name]);

            if (!$this->bootstrapManager()->hasBootstrapped(DrupalBootLevels::ROOT)) {
                // Unable to progress in the bootstrap. Give friendly error message.
                throw new CommandNotFoundException(dt('Command !command was not found. Pass --root or a @siteAlias in order to run Drupal-specific commands.', ['!command' => $name]));
            }

            // Try to find it again, now that we bootstrapped as far as possible.
            try {
                return parent::find($name);
            } catch (CommandNotFoundException $e) {
                if (!$this->bootstrapManager()->hasBootstrapped(DrupalBootLevels::DATABASE)) {
                    // Unable to bootstrap to DB. Give targetted error message.
                    throw new CommandNotFoundException(dt('Command !command was not found. Drush was unable to query the database. As a result, many commands are unavailable. Re-run your command with --debug to see relevant log messages.', ['!command' => $name]));
                }
                if (!$this->bootstrapManager()->hasBootstrapped(DrupalBootLevels::FULL)) {
                    // Unable to fully bootstrap. Give targetted error message.
                    throw new CommandNotFoundException(dt('Command !command was not found. Drush successfully connected to the database but was unable to fully bootstrap your site. As a result, many commands are unavailable. Re-run your command with --debug to see relevant log messages.', ['!command' => $name]));
                } else {
                    // We fully bootstrapped but still could not find command. Rethrow.
                    throw $e;
                }
            }
        }
    }

    /**
     * If a command is annotated @obsolete, then we will throw an exception
     * immediately; the command will not run, and no hooks will be called.
     */
    protected function checkObsolete($command)
    {
        if (!$command instanceof AnnotatedCommand) {
            return;
        }

        $annotationData = $command->getAnnotationData();
        if (!$annotationData->has('obsolete')) {
            return;
        }

        $obsoleteMessage = $command->getDescription();
        throw new \Exception($obsoleteMessage);
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
    public function configureAndRegisterCommands(InputInterface $input, OutputInterface $output, $commandfileSearchpath, ClassLoader $classLoader)
    {
        // Symfony will call this method for us in run() (it will be
        // called again), but we want to call it up-front, here, so that
        // our $input and $output objects have been appropriately
        // configured in case we wish to use them (e.g. for logging) in
        // any of the configuration steps we do here.
        $this->configureIO($input, $output);

        // Directly add the yaml-cli commands.
        $this->addCommands($this->serviceManager->instantiateYamlCliCommands());

        // Find the command handlers that we can instantiate without bootstrapping Drupal
        $commandClasses = $this->serviceManager->discover($commandfileSearchpath, '\Drush');

        // Uncomment the lines below to use Console's built in help and list commands.
        // unset($commandClasses[__DIR__ . '/Commands/help/HelpCommands.php']);
        // unset($commandClasses[__DIR__ . '/Commands/help/ListCommands.php']);

        // Instantiate our command handler objects with the service manager
        // (handles 'createEarly' static factories)
        $commandInstances = $this->serviceManager->instantiateServices($commandClasses, Drush::getContainer());

        // Register our commands with Robo, our application framework.
        // Note that Robo::register can accept either Annotated Command
        // command handlers or Symfony Console Command objects.
        Robo::register($this, $commandInstances);
    }

    /**
     * Renders a caught exception. Omits the command docs at end.
     */
    public function renderException(\Exception $e, OutputInterface $output)
    {
        $output->writeln('', OutputInterface::VERBOSITY_QUIET);

        $this->doRenderException($e, $output);
    }

    /**
     * Renders a caught Throwable. Omits the command docs at end.
     */
    public function renderThrowable(\Throwable $e, OutputInterface $output): void
    {
        $output->writeln('', OutputInterface::VERBOSITY_QUIET);

        $this->doRenderThrowable($e, $output);
    }
}
