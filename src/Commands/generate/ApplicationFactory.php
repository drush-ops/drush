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
use DrupalCodeGenerator\Twig\TwigEnvironment;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Boot\DrupalBootLevels;
use Drush\Drupal\DrushServiceModifier;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Loader\FilesystemLoader;
use Webmozart\PathUtil\Path;

class ApplicationFactory implements AutoloaderAwareInterface
{
    use AutoloaderAwareTrait;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $config;

    public function __construct(LoggerInterface $logger, $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Creates Drush generate application.
     */
    public function create(): Application
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

    protected function discoverGlobalPathsDeprecated(): array
    {
        $config_paths = $this->getConfig()->get('runtime.commandfile.paths', []);
        foreach ($config_paths as $path) {
            $global_paths[] = Path::join($path, 'Generators');
            $global_paths[] = Path::join($path, 'src/Generators');
        }
        return array_filter($global_paths, 'file_exists');
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
     * @return Generator[]
     * @throws \ReflectionException
     */
    protected function getGenerators(array $classes): array
    {
        return array_map(
            function (string $class): Generator {
                return new $class();
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
