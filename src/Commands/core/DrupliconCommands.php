<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class DrupliconCommands extends DrushCommands
{
    protected $printed = false;

    /**
     * @hook option *
     * @option druplicon Shows the druplicon as glorious ASCII art.
     */
    public function optionset($options = ['druplicon' => false])
    {
    }

    /**
     * Print druplicon as post-command output.
     *
     * @hook post-command *
     */
    public function druplicon($result, CommandData $commandData)
    {
        // If one command does a drush_invoke to another command,
        // then this hook will be called multiple times. Only print
        // once.  (n.b. If drush_invoke_process passes along the
        // --druplicon option, then we still get multiple output)
        if ($this->printed) {
            return;
        }
        $this->printed = true;
        $annotationData = $commandData->annotationData();
        $commandName = $annotationData['command'];
        if ($commandData->input()->hasOption('druplicon') && $commandData->input()->getOption('druplicon')) {
            $this->logger()->debug(dt('Displaying Druplicon for "!command" command.', ['!command' => $commandName]));
            $misc_dir = DRUSH_BASE_PATH . '/misc';
            if ($commandData->input()->getOption('no-ansi')) {
                $content = file_get_contents($misc_dir . '/druplicon-no_color.txt');
            } else {
                $content = file_get_contents($misc_dir . '/druplicon-color.txt');
            }
            $commandData->output()->writeln($content);
        }
    }
}
