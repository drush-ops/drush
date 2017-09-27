<?php

namespace Drush\Boot;

use Drush\Drush;
use Drush\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;

use Symfony\Component\Console\Input\ArgvInput;

abstract class BaseBoot implements Boot, LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    protected $uri;

    public function __construct()
    {
    }

    public function findUri($root, $uri)
    {
        return 'default';
    }

    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    public function validRoot($path)
    {
    }

    public function getVersion($root)
    {
    }

    public function commandDefaults()
    {
    }

    public function enforceRequirement(&$command)
    {
        drush_enforce_requirement_bootstrap_phase($command);
        drush_enforce_requirement_core($command);
        drush_enforce_requirement_drush_dependencies($command);
    }

    public function reportCommandError($command)
    {
        // Set errors related to this command.
        $args = implode(' ', drush_get_arguments());
        if (isset($command) && is_array($command)) {
            foreach ($command['bootstrap_errors'] as $key => $error) {
                drush_set_error($key, $error);
            }
            drush_set_error('DRUSH_COMMAND_NOT_EXECUTABLE', dt("The Drush command '!args' could not be executed.", array('!args' => $args)));
        } elseif (!empty($args)) {
            drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt("The Drush command '!args' could not be found. Use 'drush core-status' to verify that Drupal is found and bootstrapped successfully. Look for 'Drupal bootstrap : Successful' in its output.", array('!args' => $args)));
        }
        // Set errors that occurred in the bootstrap phases.
        $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', array());
        foreach ($errors as $code => $message) {
            drush_set_error($code, $message);
        }
    }

    // @deprecated
    public function bootstrapAndDispatch()
    {
        $phases = $this->bootstrapInitPhases();

        $return = '';
        $command_found = false;
        _drush_bootstrap_output_prepare();
        foreach ($phases as $phase) {
            if (drush_bootstrap_to_phase($phase)) {
                $command = drush_parse_command();
                if (is_array($command)) {
                    $command += $this->commandDefaults();
                    // Insure that we have bootstrapped to a high enough
                    // phase for the command prior to enforcing requirements.
                    $bootstrap_result = drush_bootstrap_to_phase($command['bootstrap']);
                    $this->enforceRequirement($command);

                    if ($bootstrap_result && empty($command['bootstrap_errors'])) {
                        $this->logger->log(LogLevel::BOOTSTRAP, dt("Found command: !command (commandfile=!commandfile)", array('!command' => $command['command'], '!commandfile' => $command['commandfile'])));
                        $command_found = true;

                        // Special case. Force 'help' command if --help option was specified.
                        if (drush_get_option('help')) {
                            $implemented = drush_get_commands();
                            $command = $implemented['help'];
                            $command['arguments']['name'] = drush_get_arguments()[0];
                            $command['allow-additional-options'] = true;
                        }

                        // Dispatch the command(s).
                        $return = drush_dispatch($command);

                        if (drush_get_context('DRUSH_DEBUG') && !drush_get_context('DRUSH_QUIET')) {
                            drush_print_timers();
                        }
                        break;
                    }
                }
            } else {
                break;
            }
        }

        // TODO: If we could not find a legacy Drush command, try running a
        // command via the Symfony application. See also drush_main() in preflight.inc;
        // ultimately, the Symfony application should be called from there.
        if (!$command_found && isset($command) && empty($command['bootstrap_errors'])) {
            $application = Drush::getApplication();
            $args = drush_get_arguments();
            if (count($args)) {
                $name = $args[0];
                if ($this->hasRegisteredSymfonyCommand($application, $name)) {
                    $command_found = true;
                    $input = drush_symfony_input();
                    $this->logger->log(LogLevel::DEBUG_NOTIFY, dt("Dispatching with Symfony application as a fallback, since no native Drush command was found. (Set DRUSH_SYMFONY environment variable to skip Drush dispatch.)"));
                    $application->run($input);
                }
            }
        }

        if (!$command_found) {
            // If we reach this point, command doesn't fit requirements or we have not
            // found either a valid or matching command.
            $this->reportCommandError($command);
        }

        // Prevent a '1' at the end of the output.
        if ($return === true) {
            $return = '';
        }

        return $return;
    }

    public function bootstrapPhases()
    {
        return [
            DRUSH_BOOTSTRAP_DRUSH => 'bootstrapDrush',
        ];
    }

    public function bootstrapPhaseMap()
    {
        return [
            'none' => DRUSH_BOOTSTRAP_DRUSH,
            'drush' => DRUSH_BOOTSTRAP_DRUSH,
            'max' => DRUSH_BOOTSTRAP_MAX,
            'root' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
            'site' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
            'configuration' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
            'database' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
            'full' => DRUSH_BOOTSTRAP_DRUPAL_FULL
        ];
    }

    public function lookUpPhaseIndex($phase)
    {
        $phaseMap = $this->bootstrapPhaseMap();
        if (isset($phaseMap[$phase])) {
            return $phaseMap[$phase];
        }

        if ((substr($phase, 0, 16) != 'DRUSH_BOOTSTRAP_') || (!defined($phase))) {
            return;
        }
        return constant($phase);
    }

    public function bootstrapDrush()
    {
    }

    protected function hasRegisteredSymfonyCommand($application, $name)
    {
        try {
            $application->get($name);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    protected function inflect($object)
    {
        $container = $this->getContainer();
        if ($object instanceof \Robo\Contract\ConfigAwareInterface) {
            $object->setConfig($container->get('config'));
        }
        if ($object instanceof \Psr\Log\LoggerAwareInterface) {
            $object->setLogger($container->get('logger'));
        }
        if ($object instanceof \League\Container\ContainerAwareInterface) {
            $object->setContainer($container->get('container'));
        }
        if ($object instanceof \Symfony\Component\Console\Input\InputAwareInterface) {
            $object->setInput($container->get('input'));
        }
        if ($object instanceof \Robo\Contract\OutputAwareInterface) {
            $object->setOutput($container->get('output'));
        }
        if ($object instanceof \Robo\Contract\ProgressIndicatorAwareInterface) {
            $object->setProgressIndicator($container->get('progressIndicator'));
        }
        if ($object instanceof \Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface) {
            $object->setHookManager($container->get('hookManager'));
        }
        if ($object instanceof \Robo\Contract\VerbosityThresholdInterface) {
            $object->setOutputAdapter($container->get('outputAdapter'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function terminate()
    {
    }
}
