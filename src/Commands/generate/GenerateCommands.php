<?php

declare(strict_types=1);

namespace Drush\Commands\generate;

use DrupalCodeGenerator\Application;
use Drush\Attributes as CLI;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Commands\generate\Generators\Drush\DrushAliasFile;
use Drush\Commands\generate\Generators\Migrate\MigrationGenerator;
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
     */
    #[CLI\Command(name: 'generate', aliases: ['gen'])]
    #[CLI\Argument(name: 'generator', description: 'A generator name. Omit to pick from available Generators.')]
    #[CLI\Option(name: 'working-dir', description: 'Absolute path to working directory.')]
    #[CLI\Option(name: 'dry-run', description: 'Output the generated code but not save it to file system.')]
    #[CLI\Option(name: 'answer', description: 'Answer to generator question.')]
    #[CLI\Option(name: 'destination', description: 'Path to a base directory for file writing.')]
    #[CLI\Usage(name: 'drush generate', description: 'Pick from available generators and then run it.')]
    #[CLI\Usage(name: 'drush generate drush-command-file', description: 'Generate a Drush commandfile for your module.')]
    #[CLI\Usage(name: 'drush generate controller --answer=Example --answer=example', description: 'Generate a controller class and pre-fill the first two questions in the wizard.')]
    #[CLI\Usage(name: 'drush generate controller -vvv --dry-run', description: 'Learn all the potential answers so you can re-run with several --answer options.')]
    #[CLI\Topics(topics: ['docs:generators'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
    public function generate(string $generator = '', $options = ['replace' => FALSE, 'working-dir' => self::REQ, 'answer' => [], 'destination' => self::REQ, 'dry-run' => false]): int
    {
        // @todo Figure out a way to inject the container.
        $container = \Drupal::getContainer();

        // @todo Implement discovery for third-party generators.
        $application = Application::create($container);
        $application->setAutoExit(FALSE);

        $application->addCommands([
            new MigrationGenerator(),
            new DrushAliasFile(),
            // @todo Update DrushCommandFile generator.
        ]);

        // Disallow default Symfony console commands.
        if ($generator == 'help' || $generator == 'list' || $generator == 'completion') {
            $generator = null;
        }

        if (!$generator) {
            $all = $application->all();
            unset($all['help'], $all['list'], $all['completion']);
            $namespaced = ListCommands::categorize($all);
            $preamble = dt('Run `drush generate [command]` and answer a few questions in order to write starter code to your project.');
            ListCommands::renderListCLI($application, $namespaced, $this->output(), $preamble);
            return self::EXIT_SUCCESS;
        }

        // Create an isolated input.
        $argv = ['dcg', $generator];

        $argv[] = '--full-path';
        if ($options['yes']) {
            $argv[] = '--replace';
        }
        if ($options['working-dir']) {
            $argv[] = '--working-dir=' . $options['working-dir'];
        }
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
        // @todo Why is this option missing?
        if (!empty($options['no-ansi'])) {
            $argv[] = '--no-ansi';
        }
        if ($options['dry-run']) {
            $argv[] = '--dry-run';
        }

        return $application->run(new ArgvInput($argv), $this->output());
    }

}
