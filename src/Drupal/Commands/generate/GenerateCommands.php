<?php

declare(strict_types=1);

namespace Drush\Drupal\Commands\generate;

use Composer\Autoload\ClassLoader;
use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Event\GeneratorInfoAlter;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Commands\help\ListCommands;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class GenerateCommands extends DrushCommands
{
    private ContainerInterface $container;
    private ClassLoader $autoloader;

    public function __construct(ContainerInterface $container, ClassLoader $autoloader)
    {
        $this->container = $container;
        $this->autoloader = $autoloader;
    }

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
    public function generate(string $generator = '', $options = ['replace' => false, 'working-dir' => self::REQ, 'answer' => [], 'destination' => self::REQ, 'dry-run' => false]): int
    {

        $this->container->get('event_dispatcher')
            ->addListener(GeneratorInfoAlter::class, [self::class, 'alterGenerators']);

        $application = (new ApplicationFactory($this->container, $this->autoloader, $this->logger()))->create();

        // Disallow default Symfony console commands.
        if ($generator == 'help' || $generator == 'completion') {
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

    /**
     * Implements hook GeneratorInfoAlter.
     */
    public static function alterGenerators(GeneratorInfoAlter $event): void
    {
        $event->generators['theme-settings']->setName('theme:settings');
        $event->generators['plugin-manager']->setName('plugin:manager');
    }
}
