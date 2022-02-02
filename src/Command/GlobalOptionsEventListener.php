<?php

namespace Drush\Command;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drush\Preflight\LegacyPreflight;

class GlobalOptionsEventListener implements EventSubscriberInterface
{
    /**
     * @{@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        // Register our 'setGlobalOptions' command to run prior to
        // command dispatch.
        return [ConsoleEvents::COMMAND => 'setGlobalOptions'];
    }

    /**
     * Before a Console command runs, examine the global
     * commandline options from the event Input, and set
     * configuration values as appropriate.
     *
     * @param ConsoleCommandEvent $event
     */
    public function setGlobalOptions(ConsoleCommandEvent $event): void
    {
        /* @var Input $input */
        $input = $event->getInput();
        $output = $event->getOutput();

        // TODO: We need a good strategy for managing global options.
        // $simulate = $input->getOption('simulate');
    }
}
