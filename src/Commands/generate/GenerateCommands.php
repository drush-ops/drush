<?php

namespace Drush\Commands\generate;

use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\ClassResolver\SimpleClassResolver;
use DrupalCodeGenerator\Command\Generator;
use DrupalCodeGenerator\GeneratorFactory;
use DrupalCodeGenerator\Helper\DrupalContext;
use DrupalCodeGenerator\Helper\Dumper;
use DrupalCodeGenerator\Helper\QuestionHelper;
use DrupalCodeGenerator\Helper\Renderer;
use DrupalCodeGenerator\Helper\ResultPrinter;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Commands\help\ListCommands;
use Drush\Drupal\DrushServiceModifier;
use Drush\Drush;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Loader\FilesystemLoader;
use DrupalCodeGenerator\Twig\TwigEnvironment;
use Webmozart\PathUtil\Path;

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
     * @param string $generator A generator name. Omit to pick from available Generators.
     * @option answer Answer to generator question.
     * @option dry-run Output the generated code but not save it to file system.
     * @option destination Absolute path to a base directory for file writing.
     * @usage drush generate
     *  Pick from available generators and then run it.
     * @usage drush generate controller
     *  Generate a controller class for your module.
     * @usage drush generate drush-command-file
     *  Generate a Drush commandfile for your module.
     * @topics docs:generators
     * @bootstrap max
     *
     * @return int
     */
    public function generate($generator = '', $options = ['answer' => [], 'destination' => self::REQ, 'dry-run' => false])
    {
        // Disallow default Symfony console commands.
        if ($generator == 'help' || $generator == 'list') {
            $generator = null;
        }

        $application = $this->createApplication();

        if (!$generator) {
            $all = $application->all();
            unset($all['help'], $all['list']);
            $namespaced = ListCommands::categorize($all);
            $preamble = dt('Run `drush generate [command]` and answer a few questions in order to write starter code to your project.');
            ListCommands::renderListCLI($application, $namespaced, $this->output(), $preamble);
            return self::EXIT_SUCCESS;
        }

        // Create an isolated input.
        $argv = ['dcg' , $generator];
        $argv[] = '--full-path';
        // annotated-command does not support short options (e.g. '-a' for answer).
        foreach ($options['answer'] as $answer) {
            $argv[] = '--answer='. $answer;
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

    /**
     * Creates Drush generate application.
     */
    private function createApplication(): Application
    {
        $application = new Application('Drupal Code Generator', Drush::getVersion());
        $application->setAutoExit(false);

        $class_resolver = new SimpleClassResolver();
        if (Drush::bootstrapManager()->hasBootstrapped(DrupalBootLevels::FULL)) {
            $container = \Drupal::getContainer();
            $class_resolver = new GeneratorClassResolver($container->get('class_resolver'));
        }
        $generator_factory = new GeneratorFactory($class_resolver, $this->logger());

        $helper_set = new HelperSet([
            new QuestionHelper(),
            new Dumper(new Filesystem()),
            new Renderer(new TwigEnvironment(new FilesystemLoader([Application::TEMPLATE_PATH]))),
            new ResultPrinter(),
            new DrupalContext($container)
        ]);
        $application->setHelperSet($helper_set);

        $dcg_generators = $generator_factory->getGenerators([Application::ROOT . '/src/Command'], Application::GENERATOR_NAMESPACE);
        $drush_generators = $generator_factory->getGenerators([__DIR__ . '/Generators'], '\Drush\Commands\generate\Generators');
        $global_generators = $this->discoverPsr4Generators();

        $module_generators = [];
        if (isset($container)) {
            if ($container->has(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)) {
                $module_generators = $container->get(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)->getCommandList();
            }
        }

        $generators = [
            ...self::filterGenerators($dcg_generators),
            ...$drush_generators,
            ...$global_generators,
            ...$module_generators,
        ];
        $application->addCommands($generators);

        return $application;
    }

    /**
     * Filter and rename DCG generators.
     * @param Generator[] $generators
     * @return array
     */
    private static function filterGenerators(array $generators): array
    {
        $generators = array_filter(
            $generators,
            static fn ($generator) =>
                !str_starts_with($generator->getName(), 'misc:d7:') &&
                !str_starts_with($generator->getName(), 'console:'),
        );
        $generators = array_map(
            function ($generator) {
                if ($generator->getName() == 'theme-file') $generator->setName('theme:file');
                if ($generator->getName() == 'theme-settings') $generator->setName('theme:settings');
                if ($generator->getName() == 'theme-settings') $generator->setName('theme:settings');
                if ($generator->getName() == 'plugin-manager') $generator->setName('plugin:manager');
                // Remove the word 'module'.
                if ($generator->getName() == 'configuration-entity') $generator->setDescription('Generates configuration entity');
                if ($generator->getName() == 'content-entity') $generator->setDescription('Generates configuration entity');
                return $generator;
            },
            $generators
        );
        return $generators;
    }

    protected function discoverGlobalPathsDeprecated()
    {
        $config_paths = $this->getConfig()->get('runtime.commandfile.paths', []);
        foreach ($config_paths as $path) {
            $global_paths[] = Path::join($path, 'Generators');
            $global_paths[] = Path::join($path, 'src/Generators');
        }
        return array_filter($global_paths, 'file_exists');
    }

    protected function discoverPsr4Generators()
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
     * @return Generator[]
     * @throws \ReflectionException
     */
    protected function getGenerators(array $classes): array
    {
        return array_map(
            function (string $class): Generator {
                return new $class;
            },
            array_filter($classes, function (string $class): bool {
                $reflectionClass = new \ReflectionClass($class);
                return $reflectionClass->isSubclassOf(Generator::class)
                    && !$reflectionClass->isAbstract()
                    && !$reflectionClass->isInterface()
                    && !$reflectionClass->isTrait();
            })
        );
    }
}
