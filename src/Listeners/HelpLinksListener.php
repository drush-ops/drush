<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
use Drush\Attributes\HelpLinks;
use Drush\Boot\DrupalBootLevels;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class HelpLinksListener
{
    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            // Support LazyCommand.
            $command = method_exists($command, 'getCommand') && $command->getCommand() ? $command->getCommand() : $command;
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflectionObject = new \ReflectionObject($code);
            $attributes = $reflectionObject->getAttributes(CLI\HelpLinks::class);
            if ($attributes !== []) {
                // Bail if this Listener has already run on this class.
                if (str_contains($command->getHelp(), 'Help topics:')) {
                    continue;
                }
                /** @var HelpLinks $instance */
                $instance = $attributes[0]->newInstance();
                $bullets = array_map(fn(\Drush\Command\HelpLinks $case) => $case->consoleLink(), $instance->links);
                $help = $command->getHelp();
                $help .= "\n\n" . self::bullets($bullets);
                $command->setHelp($help);
            }
        }
    }

    public static function bullets(array $links): string
    {
        return "Help topics:\n\n" . implode("\n", $links);
    }
}
