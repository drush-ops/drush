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
class OptionsetProcBuildListener
{
    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflection = new \ReflectionObject($code);
            $attributes = $reflection->getAttributes(CLI\OptionsetProcBuild::class);
            if (empty($attributes)) {
                continue;
            }
            $command->addOption(name: 'ssh-options', mode: InputOption::VALUE_REQUIRED, description: 'A string appended to ssh command during rsync, sql:sync, etc.');
            $command->addOption('tty', 'Create a tty (e.g. to run an interactive program).');
        }
    }
}
