<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Composer\Autoload\ClassLoader;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Consolidation\Filter\Hooks\FilterHooks;
use DrupalCodeGenerator\Command\BaseGenerator;
use Drush\Command\DrushCommandInfoAlterer;
use Drush\Commands\DrushCommands;
use Drush\Config\ConfigAwareTrait;
use Drush\Config\DrushConfig;
use Drush\Drupal\DrupalKernelTrait;
use Drush\Drush;
use Drush\Log\Logger;
use Grasmash\YamlCli\Command\GetValueCommand;
use Grasmash\YamlCli\Command\LintCommand;
use Grasmash\YamlCli\Command\UnsetKeyCommand;
use Grasmash\YamlCli\Command\UpdateKeyCommand;
use Grasmash\YamlCli\Command\UpdateValueCommand;
use Drupal\Component\DependencyInjection\ContainerInterface as DrupalContainer;
use Psr\Container\ContainerInterface as DrushContainer;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Console\Application;

/**
 * Manage Drush services.
 *
 * This class manages the various services / plugins supported by Drush.
 * The primary examples of these include:
 *
 *   - Command files
 *   - Hooks
 *   - Symfony Console commands
 *   - Command info alterers
 *   - Generators
 *
 * Most services are discovered via the PSR-4 discovery mechanism. Legacy
 * services are injected into this object by the bootstrap handler
 * (DrushBoot8) using the LegacyServiceFinder and LegacyServiceInstantiator
 * classes.
 */
class ServiceManager implements ConfigAwareInterface
{
    use ConfigAwareTrait;

    protected $generators = [];
    /** @var string[] */
    protected array $bootstrapCommandClasses = [];

    public function __construct(protected ClassLoader $autoloader)
    {
    }

    /**
     * Ensure that any discovered class that is not part of the autoloader
     * is, in fact, included.
     *
     * @param array Associative array mapping path => class.
     */
    protected function loadCommandClasses(array $commandClasses): void
    {
        foreach ($commandClasses as $file => $commandClass) {
            if (!class_exists($commandClass)) {
                include $file;
            }
        }
    }

    /**
     * Return cached of deferred commandhander objects.
     *
     * @return string[]
     *   List of class names to instantiate at bootstrap time.
     */
    public function bootstrapCommandClasses(): array
    {
        return $this->bootstrapCommandClasses;
    }

    /**
     * Discover all of the different kinds of command handler objects
     * in the places where Drush can find them. Called during preflight;
     * some command classes are returned right away, and others are saved
     * to be handled later during Drupal bootstrap.
     *
     * @param string[] $commandfileSearchpath List of directories to search
     * @param string $baseNamespace The namespace to use at the base of each
     *   search diretory. Namespace components mirror directory structure.
     *
     * @return string[]
     *   List of command classes
     */
    public function discover(array $commandfileSearchpath, string $baseNamespace): array
    {
        $commandClasses = array_unique(array_merge(
            $this->discoverCommandsFromConfiguration(),
            $this->discoverCommands($commandfileSearchpath, '\Drush'),
            $this->discoverPsr4Commands(),
            [FilterHooks::class]
        ));

        // If a command class has a static `create` method, then we will
        // postpone instantiating it until after we bootstrap Drupal.
        $this->bootstrapCommandClasses = array_filter($commandClasses, [$this, 'hasStaticCreateFactory']);

        // Remove the command classes that we put into the bootstrap command classes.
        $commandClasses = array_diff($commandClasses, $this->bootstrapCommandClasses);

        return $commandClasses;
    }

    /**
     * Discover commands explicitly declared in configuration.
     *
     * @return string[]
     *   List of command classes
     */
    public function discoverCommandsFromConfiguration(): array
    {
        $commandList = [];
        foreach ($this->config->get('drush.commands', []) as $key => $value) {
            if (is_numeric($key)) {
                $classname = $value;
                $commandList[] = $classname;
            } else {
                $classname = ltrim($key, '\\');
                $commandList[$value] = $classname;
            }
        }
        $this->loadCommandClasses($commandList);
        return array_values($commandList);
    }

    /**
     * Discovers command classes from a provided search path.
     *
     * @param string[] $directoryList List of directories to search
     * @param string $baseNamespace The namespace to use at the base of each
     *   search diretory. Namespace components mirror directory structure.
     *
     * @return string[]
     *   List of command classes
     */
    public function discoverCommands(array $directoryList, string $baseNamespace): array
    {
        $discovery = new CommandFileDiscovery();
        $discovery
            ->setIncludeFilesAtBase(true)
            ->setSearchDepth(3)
            ->ignoreNamespacePart('contrib', 'Commands')
            ->ignoreNamespacePart('custom', 'Commands')
            ->ignoreNamespacePart('src')
            ->setSearchLocations(['Commands', 'Hooks', 'Generators'])
            ->setSearchPattern('#.*(Command|Hook|Generator)s?.php$#');
        $baseNamespace = ltrim($baseNamespace, '\\');
        $commandClasses = $discovery->discover($directoryList, $baseNamespace);
        $this->loadCommandClasses($commandClasses);
        return array_values($commandClasses);
    }

    /**
     * Discover PSR-4 autoloaded classes that implement Annotated Command
     * library command handlers.
     *
     * @return string[]
     *   List of command classes
     */
    public function discoverPsr4Commands(): array
    {
        $classes = (new RelativeNamespaceDiscovery($this->autoloader))
            ->setRelativeNamespace('Drush\Commands')
            ->setSearchPattern('/.*DrushCommands\.php$/')
            ->getClasses();

        return array_filter($classes, function (string $class): bool {
            $reflectionClass = new \ReflectionClass($class);
            return $reflectionClass->isSubclassOf(DrushCommands::class)
                && !$reflectionClass->isAbstract()
                && !$reflectionClass->isInterface()
                && !$reflectionClass->isTrait();
        });
    }

    /**
     * Discover PSR-4 autoloaded classes that implement DCG generators.
     *
     * @return string[]
     *   List of generator classes
     */
    public function discoverPsr4Generators(): array
    {
        $classes = (new RelativeNamespaceDiscovery($this->autoloader))
            ->setRelativeNamespace('Drush\Generators')
            ->setSearchPattern('/.*Generator\.php$/')
            ->getClasses();

        return array_filter($classes, function (string $class): bool {
                $reflectionClass = new \ReflectionClass($class);
                return $reflectionClass->isSubclassOf(BaseGenerator::class)
                    && !$reflectionClass->isAbstract()
                    && !$reflectionClass->isInterface()
                    && !$reflectionClass->isTrait();
        });
    }

    /**
     * Discover module commands. This is the preferred way to find module
     * commands in Drush 12+.
     *
     * @return string[]
     *   List of command classes
     */
    public function discoverModuleCommands(array $directoryList, string $baseNamespace): array
    {
        $discovery = new CommandFileDiscovery();
        $discovery
            ->setIncludeFilesAtBase(true)
            ->setSearchDepth(1)
            ->ignoreNamespacePart('src')
            ->setSearchLocations(['Commands', 'Hooks', 'Generators'])
            ->setSearchPattern('#.*(Command|Hook|Generator)s?.php$#');
        $baseNamespace = ltrim($baseNamespace, '\\');
        $commandClasses = $discovery->discover($directoryList, $baseNamespace);
        return array_values($commandClasses);
    }

    /**
     * Discover command info alterers in modules.
     *
     * @param string[] $directoryList List of directories to search
     * @param string $baseNamespace The namespace to use at the base of each
     *   search diretory. Namespace components mirror directory structure.
     *
     * @return string[]
     */
    public function discoverModuleCommandInfoAlterers(array $directoryList, string $baseNamespace): array
    {
        $discovery = new CommandFileDiscovery();
        $discovery
            ->setIncludeFilesAtBase(true)
            ->setSearchDepth(1)
            ->ignoreNamespacePart('src')
            ->setSearchLocations(['CommandInfoAlterers'])
            ->setSearchPattern('#.*CommandInfoAlterer.php$#');
        $baseNamespace = ltrim($baseNamespace, '\\');
        $commandClasses = $discovery->discover($directoryList, $baseNamespace);
        return array_values($commandClasses);
    }

    /**
     * Instantiate commands from Grasmash\YamlCli that we want to expose
     * as Drush commands.
     *
     * @return Command[]
     *   List of Symfony Command objects
     */
    public function instantiateYamlCliCommands(): array
    {
        $classes_yaml = [
            GetValueCommand::class,
            LintCommand::class,
            UpdateKeyCommand::class,
            UnsetKeyCommand::class,
            UpdateValueCommand::class
        ];

        foreach ($classes_yaml as $class_yaml) {
            /** @var Command $instance */
            $instance = new $class_yaml();
            // Namespace the commands.
            $name = $instance->getName();
            $instance->setName('yaml:' . $name);
            $instance->setAliases(['y:' . $name]);
            $instance->setHelp('See https://github.com/grasmash/yaml-cli for a README and bug reports.');
            $instances[] = $instance;
        }
        return $instances;
    }

    /**
     * Instantiate objects given a lsit of classes. For each class, if it has
     * a static `create` factory, use that to instantiate it, passing both the
     * Drupal and Drush DI containers. If there is no static factory, then
     * instantiate it via 'new $class'
     *
     * @param string[] $bootstrapCommandClasses Classes to instantiate.
     * @param Drupal\Component\DependencyInjection\ContainerInterface $container
     * @param Psr\Container\ContainerInterface $drushContainer
     *
     * @return object[]
     *   List of instantiated service objects
     */
    public function instantiateServices(array $bootstrapCommandClasses, DrupalContainer $container, DrushContainer $drushContainer)
    {
        $commandHandlers = [];

        foreach ($bootstrapCommandClasses as $class) {
            $commandHandler = null;
            try {
                if ($this->hasStaticCreateFactory($class)) {
                    $commandHandler = $class::create($container, $drushContainer);
                } else {
                    $commandHandler = new $class();
                }
            } catch (\Exception $e) {
            }
            // Fail silently if the command handler could not be
            // instantiated, e.g. if it tries to fetch services from
            // a module that has not been enabled. Note that Robo::register
            // can accept either Annotated Command command handlers or
            // Symfony Console Command objects.
            if ($commandHandler) {
                $commandHandlers[] = $commandHandler;
            }
        }

        return $commandHandlers;
    }

    /**
     * Check to see if the provided class has a static `create` method.
     *
     * @param string $class The name of the class to check
     *
     * @return bool
     *   True if class has a static `create` method.
     */
    protected function hasStaticCreateFactory(string $class): bool
    {
        if (!method_exists($class, 'create')) {
            return false;
        }

        $reflectionMethod = new \ReflectionMethod($class, 'create');
        return $reflectionMethod->isStatic();
    }

    /**
     * Return generators stored here via `injectGenerators()`
     *
     * @return object[]
     *   List of instantiated generators
     */
    public function getGenerators(): array
    {
        return $this->generators;
    }

    /**
     * Store instantiated generators here for later use by the Generate commands.
     *
     * @param object[] List of instantiated generators
     */
    public function injectGenerators(array $additionalGenerators): void
    {
        $this->generators = [
            ...$this->generators,
            ...$additionalGenerators
        ];
    }
}
