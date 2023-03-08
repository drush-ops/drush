<?php

declare(strict_types=1);

namespace Drush\Boot;

use Consolidation\AnnotatedCommand\Hooks\InitializeHookInterface;
use Symfony\Component\Console\Input\InputInterface;
use Consolidation\AnnotatedCommand\AnnotationData;

/**
 * The BootstrapHook is installed as an init hook that runs before
 * all commands. If there is a `@bootstrap` annotation/attribute, then we will
 * bootstrap Drupal to the requested phase.
 */
class BootstrapHook implements InitializeHookInterface
{
    protected $bootstrapManager;

    public function __construct(BootstrapManager $bootstrapManager)
    {
        $this->bootstrapManager = $bootstrapManager;
    }

    public function initialize(InputInterface $input, AnnotationData $annotationData): void
    {
        // Get the @bootstrap annotation/attribute. If there isn't one, then assume NONE.
        $phase_long = $annotationData->get('bootstrap', 'none');
        // Ignore any extra: thats been passed in the attribute.
        $phase_long = current(explode(' ', $phase_long));
        if (is_numeric($phase_long)) {
            $phase = DrupalBootLevels::getPhaseName($phase_long);
        } else {
            $phase = current(explode(' ', $phase_long));
        }
        $bootstrap_successful = $this->bootstrapManager->bootstrapToPhase($phase, $annotationData);

        if (!$bootstrap_successful) {
            // TODO: better exception class, better exception method
            throw new \Exception('Bootstrap failed. Run your command with -vvv for more information.');
        }
    }
}
