<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes\FilterDefaultField;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class FilterOptionListener
{
    /**
     *  Add the --filter option is needed.
     */
    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            $reflection = new \ReflectionObject($command);
            $attributes = $reflection->getAttributes(FilterDefaultField::class);
            if (empty($attributes)) {
                continue;
            }
            $instance = $attributes[0]->newInstance();
            $command->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter output based on provided expression. Default field: ' . $instance->field);
        }
    }
}
