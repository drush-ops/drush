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
class OptionsetGetEditorListener
{
    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflection = new \ReflectionObject($code);
            $attributes = $reflection->getAttributes(CLI\OptionsetGetEditor::class);
            if (empty($attributes)) {
                continue;
            }
            $command->addOption(name: 'editor', mode: InputOption::VALUE_REQUIRED, description: 'A string of bash which launches user\'s preferred text editor. Defaults to <info>${VISUAL-${EDITOR-vi}}</info>.');
            $command->addOption(name: 'bg', mode: InputOption::VALUE_NONE, description: 'Launch editor in background process.');
        }
    }
}
