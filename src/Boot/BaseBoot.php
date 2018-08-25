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

    public function reportCommandError($command)
    {
        // Set errors related to this command.
        $args = implode(' ', drush_get_arguments());
        if (isset($command) && is_array($command)) {
            foreach ($command['bootstrap_errors'] as $key => $error) {
                drush_set_error($key, $error);
            }
            drush_set_error('DRUSH_COMMAND_NOT_EXECUTABLE', dt("The Drush command '!args' could not be executed.", ['!args' => $args]));
        } elseif (!empty($args)) {
            drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt("The Drush command '!args' could not be found. Use 'drush core-status' to verify that Drupal is found and bootstrapped successfully. Look for 'Drupal bootstrap : Successful' in its output.", ['!args' => $args]));
        }
        // Set errors that occurred in the bootstrap phases.
        $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', []);
        foreach ($errors as $code => $message) {
            drush_set_error($code, $message);
        }
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
        // See \Drush\Runtime\DependencyInjection::addDrushServices and
        // \Robo\Robo\addInflectors
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
        if ($object instanceof \Consolidation\SiteAlias\SiteAliasManagerAwareInterface) {
            $object->setOutputAdapter($container->get('site.alias.manager'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function terminate()
    {
    }
}
