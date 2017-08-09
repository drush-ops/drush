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
    public function __construct()
    {

    }

    public function initialize(InputInterface $input, AnnotationData $annotationData)
    {
        // Check the @bootstrap annotation. If there isn't one, then exit.
        if (!$annotationData->has('bootstrap')) {
            return;
        }

        $bootstrap = $annotationData->get('bootstrap');
    }


}
