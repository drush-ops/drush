<?php

namespace Drush\Commands\generate;

use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Commands\help\ListCommands;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Drush generate command.
 */
class GenerateCommands extends DrushCommands implements AutoloaderAwareInterface
{
    use AutoloaderAwareTrait;

    /**
     * Generate boilerplate code for modules/plugins/services etc.
     *
     * Drush asks questions so that the generated code is as polished as possible. After
     * generating, Drush lists the files that were created.
     *
     * @command generate
     * @aliases gen
     *
     * @param string $generator A generator name. Omit to pick from available Generators.
     *
     * @option answer Answer to generator question.
     * @option dry-run Output the generated code but not save it to file system.
     * @option destination Absolute path to a base directory for file writing.
     * @usage drush generate
     *  Pick from available generators and then run it.
     * @usage drush generate drush-command-file
     *  Generate a Drush commandfile for your module.
     * @usage drush generate controller --answer=Example --answer=example
     *  Generate a controller class and pre-fill the first two questions in the wizard.
     * @usage drush generate controller -vvv --dry-run
     *  Learn all the potential answers so you can re-run with several --answer options.
     * @topics docs:generators
     * @bootstrap max
     */
    public function generate(string $generator = '', $options = ['answer' => [], 'destination' => self::REQ, 'dry-run' => false]): int
    {
        // Disallow default Symfony console commands.
        if ($generator == 'help' || $generator == 'list') {
            $generator = null;
        }

        $factory = new ApplicationFactory($this->logger(), $this->getConfig());
        $factory->setAutoloader($this->autoloader());
        $application = $factory->create();

        if (!$generator) {
            $all = $application->all();
            unset($all['help'], $all['list']);
            $namespaced = ListCommands::categorize($all);
            $preamble = dt('Run `drush generate [command]` and answer a few questions in order to write starter code to your project.');
            ListCommands::renderListCLI($application, $namespaced, $this->output(), $preamble);
            return self::EXIT_SUCCESS;
        }

        // Create an isolated input.
        $argv = ['dcg', $generator];
        $argv[] = '--full-path';
        // annotated-command does not support short options (e.g. '-a' for answer).
        foreach ($options['answer'] as $answer) {
            $argv[] = '--answer=' . $answer;
        }
        if ($options['destination']) {
            $argv[] = '--destination=' . $options['destination'];
        }
        if ($options['ansi']) {
            $argv[] = '--ansi';
        }
        if ($options['no-ansi']) {
            $argv[] = '--no-ansi';
        }
        if ($options['dry-run']) {
            $argv[] = '--dry-run';
        }

        return $application->run(new ArgvInput($argv), $this->output());
    }
}
