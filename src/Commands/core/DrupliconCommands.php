<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;

final class DrupliconCommands extends DrushCommands implements ConfigAwareInterface
{
    use ConfigAwareTrait;

    protected bool $printed = false;

    #[CLI\Hook(type: HookManager::OPTION_HOOK, target: '*')]
    #[CLI\Option(name: 'druplicon', description: 'Shows the druplicon as glorious ASCII art.')]
    public function optionset($options = ['druplicon' => false]): void
    {
    }

    /**
     * Print druplicon as post-command output.
     */
    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: '*')]
    public function druplicon($result, CommandData $commandData): void
    {
        // If one command does a Drush::drush() to another command,
        // then this hook will be called multiple times. Only print
        // once.
        if ($this->printed) {
            return;
        }
        $this->printed = true;
        $annotationData = $commandData->annotationData();
        $commandName = $annotationData['command'];
        if ($commandData->input()->hasOption('druplicon') && $commandData->input()->getOption('druplicon')) {
            $this->logger()->debug(dt('Displaying Druplicon for "!command" command.', ['!command' => $commandName]));
            $misc_dir = $this->config->get('drush.base-dir') . '/misc';
            if ($commandData->input()->getOption('no-ansi')) {
                $content = file_get_contents($misc_dir . '/druplicon-no_color.txt');
            } else {
                $content = file_get_contents($misc_dir . '/druplicon-color.txt');
            }
            $commandData->output()->writeln($content);
        }
    }
}
