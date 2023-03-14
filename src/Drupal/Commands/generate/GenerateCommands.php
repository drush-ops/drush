<?php

declare(strict_types=1);

namespace Drush\Drupal\Commands\generate;

use Composer\Autoload\ClassLoader;
use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Command\Generator;
use DrupalCodeGenerator\Command\GeneratorInterface;
use DrupalCodeGenerator\Event\GeneratorInfoAlter;
use Drush\Attributes as CLI;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Commands\help\ListCommands;
use Drush\Drupal\Commands\generate\Generators\Drush\DrushAliasFile;
use Drush\Drupal\Commands\generate\Generators\Drush\DrushCommandFile;
use Drush\Drupal\DrushServiceModifier;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush generate command.
 */
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

        $application = Application::create($this->container);
        $application->setAutoExit(false);

        $global_generators = $this->discoverPsr4Generators();

        $module_generators = [];
        if ($this->container->has(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)) {
            $module_generators = $this->container->get(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)->getCommandList();
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
        $classes = (new RelativeNamespaceDiscovery($this->autoloader))
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
    public static function alterGenerators(GeneratorInfoAlter $event): void
    {
        $event->generators['theme-settings']->setName('theme:settings');
        $event->generators['plugin-manager']->setName('plugin:manager');
    }
}
