<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\Dependency;
use Drupal\Core\Extension\Extension;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

/**
 * Drush commands revealing Drupal dependencies.
 */
class DrupalDependenciesCommands extends DrushCommands
{
    public const WHY_MODULE = 'why:module';
    public const WHY_CONFIG = 'why:config';

    private const CIRCULAR_REFERENCE = '***circular***';
    private array $dependents = [];
    private array $tree = [];
    private array $relation = [];
    private array $dependencies = [
      'module-module' => [],
      'config-module' => [],
      'config-config' => [],
    ];

    #[CLI\Command(name: self::WHY_MODULE, aliases: ['wm'])]
    #[CLI\Help(description: 'List all objects (modules, configurations) depending on a given module')]
    #[CLI\Argument(name: 'module', description: 'The module to check dependents for')]
    #[CLI\Option(
        name: 'dependent-type',
        description: 'Type of dependents: module, config',
        suggestedValues: ['module', 'config']
    )]
    #[CLI\Option(
        name: 'only-installed',
        description: 'Only check for installed modules'
    )]
    #[CLI\Usage(
        name: 'drush why:module node --dependent-type=module',
        description: 'Show all installed modules depending on node module'
    )]
    #[CLI\Usage(
        name: 'drush why:module node --dependent-type=module --no-only-installed',
        description: 'Show all modules, including uninstalled, depending on node module'
    )]
    #[CLI\Usage(
        name: 'drush why:module node --dependent-type=config',
        description: 'Show all configuration entities depending on node module'
    )]
    #[CLI\Usage(
        name: 'drush why:module node --dependent-type=config --format=json',
        description: 'Return config entity dependents as JSON'
    )]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function dependentsOfModule(string $module, array $options = [
        'dependent-type' => InputOption::VALUE_REQUIRED,
        'only-installed' => true,
        'format' => '',
    ]): mixed
    {
        if ($options['dependent-type'] === 'module') {
            $this->buildDependents($this->dependencies['module-module']);
        } else {
            $this->scanConfigs();
            $this
                ->buildDependents($this->dependencies['config-module'])
                ->buildDependents($this->dependencies['config-config']);
        }

        if (!isset($this->dependents[$module])) {
            $this->logger()->notice(dt('No @type depends on @module', [
                '@module' => $module,
                '@type' => $options['dependent-type'] === 'module' ? dt('other module') : dt('config entity'),
            ]));
            return null;
        }

        $this->buildTree($module);

        if (empty($options['format'])) {
            return $this->drawTree($module);
        }

        return new UnstructuredData($this->tree);
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'why:module')]
    public function validateDependentsOfModule(CommandData $commandData): void
    {
        $type = $commandData->input()->getOption('dependent-type');
        if (empty($type)) {
            throw new \InvalidArgumentException("The --dependent-type option is mandatory");
        }
        if (!in_array($type, ['module', 'config'], true)) {
            throw new \InvalidArgumentException(
                "The --dependent-type option can take only 'module' or 'config' as value"
            );
        }

        $notOnlyInstalled = $commandData->input()->getOption('no-only-installed');
        if ($notOnlyInstalled && $type === 'config') {
            throw new \InvalidArgumentException("Cannot use --dependent-type=config together with --no-only-installed");
        }

        $installedModules = \Drupal::getContainer()->getParameter('container.modules');
        $module = $commandData->input()->getArgument('module');
        if ($type === 'module') {
            $this->dependencies['module-module'] = array_map(function (Extension $extension): array {
                return array_map(function (string $dependencyString) {
                    return Dependency::createFromString($dependencyString)->getName();
                }, $extension->info['dependencies']);
            }, \Drupal::service('extension.list.module')->getList());

            if (!$notOnlyInstalled) {
                $this->dependencies['module-module'] = array_intersect_key(
                    $this->dependencies['module-module'],
                    $installedModules
                );
            }
            if (!isset($this->dependencies['module-module'][$module])) {
                throw new \InvalidArgumentException(dt('Invalid @module module', [
                    '@module' => $module,
                ]));
            }
        } elseif (!isset($installedModules[$module])) {
            throw new \InvalidArgumentException(dt('Invalid @module module', [
                '@module' => $module,
            ]));
        }
    }

    #[CLI\Command(name: self::WHY_CONFIG, aliases: ['wc'])]
    #[CLI\Help(description: 'List all config entities depending on a given config entity')]
    #[CLI\Argument(name: 'config', description: 'The config entity to check dependents for')]
    #[CLI\Usage(
        name: 'drush why:config node.type.article',
        description: 'Show all config entities modules depending on node.type.article'
    )]
    #[CLI\Usage(
        name: 'drush why:config node.type.article --format=yaml',
        description: 'Return config entity dependents as YAML'
    )]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function dependentsOfConfig(string $config, array $options = [
        'format' => '',
    ]): mixed
    {
        $this->scanConfigs(false);
        $this->buildDependents($this->dependencies['config-config']);

        if (!isset($this->dependents[$config])) {
            $this->logger()->notice(dt('No other config entity depends on @config', [
                '@config' => $config,
            ]));
            return null;
        }

        $this->buildTree($config);

        if (empty($options['format'])) {
            return $this->drawTree($config);
        }

        return new UnstructuredData($this->tree);
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'why:config')]
    public function validateDependentsOfConfig(CommandData $commandData): void
    {
        $configName = $commandData->input()->getArgument('config');
        $configManager = \Drupal::getContainer()->get('config.manager');
        if (!$configManager->loadConfigEntityByName($configName)) {
            throw new \InvalidArgumentException(dt('Invalid @config config entity', [
                '@config' => $configName,
            ]));
        }
    }

    /**
     * @param string $dependency
     * @param array $path
     */
    protected function buildTree(string $dependency, array $path = []): void
    {
        $path[] = $dependency;
        foreach ($this->dependents[$dependency] as $dependent) {
            if (isset($this->relation[$dependency]) && $this->relation[$dependency] === $dependent) {
                // This relation has been already defined on other path. We mark
                // it as circular reference.
                NestedArray::setValue($this->tree, [
                    ...$path,
                    ...[$dependent],
                ], $dependent . ':' . self::CIRCULAR_REFERENCE);
                continue;
            }

            // Save this relation to avoid infinite circular references.
            $this->relation[$dependency] = $dependent;

            if (isset($this->dependents[$dependent])) {
                $this->buildTree($dependent, $path);
            } else {
                NestedArray::setValue($this->tree, [...$path, ...[$dependent]], $dependent);
            }
        }
    }

    /**
     * @param array $dependenciesPerDependent
     * @return $this
     */
    protected function buildDependents(array $dependenciesPerDependent): static
    {
        foreach ($dependenciesPerDependent as $dependent => $dependencies) {
            foreach ($dependencies as $dependency) {
                $this->dependents[$dependency][$dependent] = $dependent;
            }
        }

        // Make dependents order predictable.
        foreach ($this->dependents as $dependency => $dependents) {
            ksort($this->dependents[$dependency]);
        }
        ksort($this->dependents);

        return $this;
    }

    /**
     * @param bool $scanModuleDependencies
     */
    protected function scanConfigs(bool $scanModuleDependencies = true): void
    {
        $entityTypeManager = \Drupal::entityTypeManager();
        $configTypeIds = array_keys(
            array_filter($entityTypeManager->getDefinitions(), function (EntityTypeInterface $entityType): bool {
                return $entityType->entityClassImplements(ConfigEntityInterface::class);
            })
        );
        foreach ($configTypeIds as $configTypeId) {
            /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $config */
            foreach ($entityTypeManager->getStorage($configTypeId)->loadMultiple() as $config) {
                $dependencies = $config->getDependencies();
                $name = $config->getConfigDependencyName();
                if ($scanModuleDependencies && !empty($dependencies['module'])) {
                    $this->dependencies['config-module'][$name] = $dependencies['module'];
                }
                if (!empty($dependencies['config'])) {
                    $this->dependencies['config-config'][$name] = $dependencies['config'];
                }
            }
        }
    }

    private function drawTree(string $dependency): string
    {
        $recursiveArrayIterator = new \RecursiveArrayIterator(current($this->tree));
        $recursiveTreeIterator = new \RecursiveTreeIterator(
            $recursiveArrayIterator,
            \RecursiveTreeIterator::SELF_FIRST,
        );
        $recursiveTreeIterator->setPrefixPart(\RecursiveTreeIterator::PREFIX_END_HAS_NEXT, '├─');
        $recursiveTreeIterator->setPrefixPart(\RecursiveTreeIterator::PREFIX_END_LAST, '└─');
        $recursiveTreeIterator->setPrefixPart(\RecursiveTreeIterator::PREFIX_MID_HAS_NEXT, '│ ');
        $canvas = [];
        $canvas[] = $dependency;
        foreach ($recursiveTreeIterator as $row => $value) {
            $key = $recursiveTreeIterator->getInnerIterator()->key();
            $current = $recursiveTreeIterator->getInnerIterator()->current();
            $label = $row;
            if ($key . ':' . self::CIRCULAR_REFERENCE === $current) {
                $label .= ' <info>(' . dt('circular') . ')</info>';
            }
            $canvas[] = $label;
        }
        return implode(PHP_EOL, $canvas);
    }
}
