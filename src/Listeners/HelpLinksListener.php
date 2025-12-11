<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
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
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflectionObject = new \ReflectionObject($code);
            $attributes = $reflectionObject->getAttributes(CLI\HelpLinks::class);
            if (!empty($attributes)) {
                // Bail if this Listener has already run on this class.
                if (str_contains($command->getHelp(), 'Help topics:')) {
                    continue;
                }
                /** @var \Drush\Attributes\HelpLinks $instance */
                $instance = $attributes[0]->newInstance();
                $bullets = array_map(fn($case) => $case->consoleLink(), $instance->links);
                $help = $command->getHelp();
                $help .= "\n\n" . self::bullets($bullets);
                $command->setHelp($help);
            }
        }
    }

    public static function bullets(array $links)
    {
        return "Help topics:\n" . implode("\n", $links);
    }
}
