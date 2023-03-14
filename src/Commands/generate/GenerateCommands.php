<?php

declare(strict_types=1);

namespace Drush\Commands\generate;

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\ClassResolver\SimpleClassResolver;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Command\Generator;
use DrupalCodeGenerator\Command\GeneratorInterface;
use DrupalCodeGenerator\Event\GeneratorInfoAlter;
use Drush\Attributes as CLI;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Commands\generate\Generators\Drush\DrushAliasFile;
use Drush\Commands\generate\Generators\Drush\DrushCommandFile;
use Drush\Commands\help\ListCommands;
use Drush\Drupal\DrushServiceModifier;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Drush generate command.
 */
final class GenerateCommands extends DrushCommands implements AutoloaderAwareInterface
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
    public function generate(string $generator = '', $options = ['replace' => false, 'working-dir' => self::REQ, 'answer' => [], 'destination' => self::REQ, 'dry-run' => false]): int
    {
        // @todo Figure out a way to inject the container.
        $container = \Drupal::getContainer();

        $container->get('event_dispatcher')
            ->addListener(GeneratorInfoAlter::class, [self::class, 'alterGenerators']);

        $application = Application::create($container);
        $application->setAutoExit(false);

        $global_generators = $this->discoverPsr4Generators();

        $module_generators = [];
        if ($container->has(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)) {
            $module_generators = $container->get(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)->getCommandList();
        }

        $generators = [
            new DrushCommandFile(),
            new DrushAliasFile(),
            ...$global_generators,
            ...$module_generators,
        ];
        $application->addCommands($generators);

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

    protected function discoverPsr4Generators(): array
    {
        $classes = (new RelativeNamespaceDiscovery($this->autoloader()))
            ->setRelativeNamespace('Drush\Generators')
            ->setSearchPattern('/.*Generator\.php$/')->getClasses();
        $classes = $this->filterExists($classes);
        return $this->getGenerators($classes);
    }

    /**
     * Check each class for existence.
     *
     * @param array $classes
     * @return array
     */
    protected function filterExists(array $classes): array
    {
        $exists = [];
        foreach ($classes as $class) {
            try {
                // DCG v1 generators extend a non-existent class, so this check is needed.
                if (class_exists($class)) {
                    $exists[] = $class;
                }
            } catch (\Throwable $e) {
                $this->logger()->notice($e->getMessage());
            }
        }
        return $exists;
    }

    /**
     * Validate and instantiate generator classes.
     *
     * @param array $classes
     * @return BaseGenerator[]
     * @throws \ReflectionException
     */
    protected function getGenerators(array $classes): array
    {
        return array_map(
            function (string $class): BaseGenerator {
                return new $class();
            },
            array_filter($classes, function (string $class): bool {
                $reflectionClass = new \ReflectionClass($class);
                return $reflectionClass->isSubclassOf(BaseGenerator::class)
                    && !$reflectionClass->isAbstract()
                    && !$reflectionClass->isInterface()
                    && !$reflectionClass->isTrait();
            })
        );
    }

    /**
     * Implements hook GeneratorInfoAlter.
     */
    public static function alterGenerators(GeneratorInfoAlter $event): void {
        $event->generators['theme-settings']->setName('theme:settings');
        $event->generators['plugin-manager']->setName('plugin:manager');
    }
}
