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

    /**
     * @throws \Drush\Exceptions\BootstrapException
     */
    public function initialize(InputInterface $input, AnnotationData $annotationData)
    {
        // Get the @bootstrap annotation. If there isn't one, then assume NONE.
        $phase_long = $annotationData->get('bootstrap', 'none');
        $phase = current(explode(' ', $phase_long));
        $this->bootstrapManager->bootstrapToPhase($phase, $annotationData);
    }
}
