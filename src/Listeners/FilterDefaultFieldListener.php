<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class FilterDefaultFieldListener
{
    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflectionObject = new \ReflectionObject($code);
            // Add the --filter option if the command has a FilterDefaultField attribute.
            $attributes = $reflectionObject->getAttributes(CLI\FilterDefaultField::class);
            if (!empty($attributes)) {
                $instance = $attributes[0]->newInstance();
                $command->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter output based on provided expression. Default field: ' . $instance->field);
            }
        }
    }
}
