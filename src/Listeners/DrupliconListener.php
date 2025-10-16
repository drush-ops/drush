<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes\Bootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Event\ConsoleDefinitionsEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(method: 'onConsoleDefinitionEvent')]
#[AsEventListener(method: 'onConsoleTerminateEvent')]
#[Bootstrap(level: DrupalBootLevels::NONE)]
final class DrupliconListener
{
    use AutowireTrait;

    private bool $printed = false;

    public function __construct(
        protected LoggerInterface $logger,
        protected DrushConfig $drushConfig,
    ) {
    }

    public function onConsoleDefinitionEvent(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            $command->addOption(name: 'druplicon', mode: InputOption::VALUE_NONE, description: 'Shows the druplicon as glorious ASCII art.');
        }
    }

    public function onConsoleTerminateEvent(ConsoleTerminateEvent $event): void
    {
        // If one command does a Drush::drush() to another command,
        // then this Listener will be called multiple times. Only print
        // once.
        if ($this->printed) {
            return;
        }
        $this->printed = true;
        if ($event->getInput()->hasOption('druplicon') && $event->getInput()->getOption('druplicon')) {
            $misc_dir = $this->drushConfig->get('drush.base-dir') . '/misc';
            if ($event->getInput()->getOption('no-ansi')) {
                $content = file_get_contents($misc_dir . '/druplicon-no_color.txt');
            } else {
                $content = file_get_contents($misc_dir . '/druplicon-color.txt');
            }
            $event->getOutput()->writeln($content);
        }
    }
}
