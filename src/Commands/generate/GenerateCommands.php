<?php

declare(strict_types=1);

namespace Drush\Commands\generate;

use Drush\Attributes as CLI;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\DrushCommands;
use Drush\Commands\help\ListCommands;
use Psr\Container\ContainerInterface as DrushContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class GenerateCommands extends DrushCommands
{
    const GENERATE = 'generate';

    protected function __construct(
        private ContainerInterface $container,
        private DrushContainer $drush_container,
    ) {
    }

    public static function create(ContainerInterface $container, DrushContainer $drush_container): self
    {
        $commandHandler = new static(
            $container,
            $drush_container,
        );

        return $commandHandler;
    }

    /**
     * Generate boilerplate code for modules/plugins/services etc.
     *
     * Drush asks questions so that the generated code is as polished as possible. After
     * generating, Drush lists the files that were created.
     *
     * See https://github.com/Chi-teck/drupal-code-generator for a README and bug reports.
     */
    #[CLI\Command(name: self::GENERATE, aliases: ['gen'])]
    #[CLI\Argument(name: 'generator', description: 'A generator name. Omit to pick from available Generators.')]
    #[CLI\Option(name: 'working-dir', description: 'Absolute path to working directory.')]
    #[CLI\Option(name: 'dry-run', description: 'Output the generated code but not save it to file system.')]
    #[CLI\Option(name: 'answer', description: 'Answer to generator question.')]
    #[CLI\Option(name: 'destination', description: 'Path to a base directory for file writing.')]
    #[CLI\Usage(name: 'drush generate', description: 'Pick from available generators and then run it.')]
    #[CLI\Usage(name: 'drush generate drush-command-file', description: 'Generate a Drush commandfile for your module.')]
    #[CLI\Usage(name: 'drush generate controller --answer=Example --answer=example', description: 'Generate a controller class and pre-fill the first two questions in the wizard.')]
    #[CLI\Usage(name: 'drush generate controller -vvv --dry-run', description: 'Learn all the potential answers so you can re-run with several --answer options.')]
    #[CLI\Topics(topics: [DocsCommands::GENERATORS])]
    #[CLI\Complete(method_name_or_callable: 'generatorNameComplete')]
    public function generate(string $generator = '', $options = ['replace' => false, 'working-dir' => self::REQ, 'answer' => [], 'destination' => self::REQ, 'dry-run' => false]): int
    {
        $application = (new ApplicationFactory($this->container, $this->drush_container, $this->logger()))->create();

        if (!$generator || $generator == 'list') {
            $all = $application->all();
            unset($all['help'], $all['list'], $all['completion']);
            $namespaced = ListCommands::categorize($all);
            $preamble = dt('Run `drush generate [command]` and answer a few questions in order to write starter code to your project.');
            ListCommands::renderListCLI($application, $namespaced, $this->output(), $preamble);
            return self::EXIT_SUCCESS;
        }

        // Symfony console app does not provide any way to remove registered commands.
        if ($generator === 'completion') {
            $this->io()->getErrorStyle()->error('Command "completion" is not defined.');
            return Command::FAILURE;
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

    /**
     * Generates completion for generator names.
     */
    public function generatorNameComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('generator')) {
            $application = (new ApplicationFactory($this->container, $this->drush_container, $this->logger()))->create(
            );
            foreach ($application->all() as $name => $command) {
                if ($command->isEnabled() && !$command->isHidden()) {
                    $suggestions->suggestValue($name);
                }
            }
        }
    }
}
