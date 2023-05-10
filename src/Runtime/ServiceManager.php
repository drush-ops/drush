<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Composer\Autoload\ClassLoader;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;
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
use League\Container\Container as DrushContainer;
use League\Container\Container;
use Psr\Container\ContainerInterface;
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

    public function __construct(protected ClassLoader $autoloader)
    {
    }

    /**
     * Ensure that any discovered class that is not part of the autoloader
     * is, in fact, included.
     */
    protected function loadCommandClasses($commandClasses)
    {
        foreach ($commandClasses as $file => $commandClass) {
            if (!class_exists($commandClass)) {
                include $file;
            }
        }
    }

    /**
     * Discover commands explicitly declared in configuration.
     *
     * @return string[]
     */
    public function discoverCommandsFromConfiguration()
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
     * @return string[]
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

    public function getGenerators()
    {
        return $this->generators;
    }

    public function injectGenerators($additionalGenerators)
    {
        $this->generators = [
            ...$this->generators,
            ...$additionalGenerators
        ];
    }
}
