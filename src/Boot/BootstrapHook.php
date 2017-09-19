<?php

namespace Drush\Boot;

use Consolidation\AnnotatedCommand\Hooks\InitializeHookInterface;
use Symfony\Component\Console\Input\InputInterface;
use Consolidation\AnnotatedCommand\AnnotationData;

/**
 * The BootstrapHook is installed as an init hook that runs before
 * all commands. If there is a `@bootstrap` annotation, then we will
 * bootstrap Drupal to the requested phase.
 */
class BootstrapHook implements InitializeHookInterface
{
    protected $bootstrapManager;

    public function __construct(BootstrapManager $bootstrapManager)
    {
        $this->bootstrapManager = $bootstrapManager;
    }

    public function initialize(InputInterface $input, AnnotationData $annotationData)
    {
        // Check the @bootstrap annotation. If there isn't one, then exit.
        if (!$annotationData->has('bootstrap')) {
            return;
        }

        $phase = $annotationData->get('bootstrap');
        $bootstrap_successful = $this->bootstrapManager->bootstrapToPhase($phase);

        if (!$bootstrap_successful) {
            // TODO: better exception class, better exception method
            throw new \Exception('Bootstrap failed.');
        }
    }
}
