<?php

declare(strict_types=1);

namespace Drush\EventSubscriber\sanitize;

use Drush\Doctor\Commands\sql\SanitizeCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputOption;

/**
 * Helpers for subscribers that extend sql:sanitize.
 */
trait SanitizeOptionsTrait
{
    /**
     * Adds options to sql:sanitize from a ConsoleEvents::COMMAND subscriber.
     *
     * Options are added whichever command is running, so that help, list and
     * shell completion describe them too. Symfony binds and validates input
     * after this event, so the options are usable on the command line.
     *
     * @param InputOption[] $options
     */
    protected function addSanitizeOptions(ConsoleCommandEvent $event, array $options): void
    {
        $application = $event->getCommand()?->getApplication();
        if (!$application?->has(SanitizeCommand::NAME)) {
            return;
        }
        $command = $application->get(SanitizeCommand::NAME);
        $native = $command->getNativeDefinition();
        // Once Symfony has merged in the application's options, the command
        // reads its input from this second definition.
        $merged = $command->getDefinition();
        foreach ($options as $option) {
            // The event fires again when one command runs another.
            if ($native->hasOption($option->getName())) {
                continue;
            }
            $native->addOption($option);
            if ($merged !== $native) {
                $merged->addOption($option);
            }
        }
    }

    /**
     * Whether sql:sanitize was confirmed and it is time to sanitize.
     */
    protected function isSanitizing(ConsoleTerminateEvent $event): bool
    {
        return $event->getCommand()?->getName() === SanitizeCommand::NAME && $event->getExitCode() === Command::SUCCESS;
    }
}
