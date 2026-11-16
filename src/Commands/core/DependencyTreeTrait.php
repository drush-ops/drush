<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Command\OutputFormat;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Builds and renders a tree of dependents for why:module and why:config.
 *
 * Classes using this trait inject EntityTypeManagerInterface as
 * $this->entityTypeManager.
 */
trait DependencyTreeTrait
{
    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;

    /**
     * Marks a dependent that was already reached on another path.
     */
    private const string CIRCULAR_REFERENCE = '***circular***';

    /**
     * Dependents grouped by dependency.
     */
    private array $dependents = [];

    /**
     * The computed dependency tree.
     */
    private array $tree = [];

    /**
     * Visited dependency -> dependent relations, to detect circular references.
     */
    private array $relation = [];

    /**
     * Clears what a previous run computed. Commands are shared services.
     */
    protected function resetTree(): void
    {
        $this->dependents = $this->tree = $this->relation = [];
    }

    /**
     * Whether anything depends on $dependency.
     */
    protected function hasDependents(string $dependency): bool
    {
        return isset($this->dependents[$dependency]);
    }

    /**
     * Records the reverse relation: dependency -> dependents.
     *
     * @param array<string, string[]> $dependenciesPerDependent
     */
    protected function buildDependents(array $dependenciesPerDependent): void
    {
        foreach ($dependenciesPerDependent as $dependent => $dependencies) {
            foreach ($dependencies as $dependency) {
                $this->dependents[$dependency][$dependent] = $dependent;
            }
        }

        // Make the order predictable.
        foreach (array_keys($this->dependents) as $dependency) {
            ksort($this->dependents[$dependency]);
        }
        ksort($this->dependents);
    }

    /**
     * Builds the nested dependency tree.
     */
    protected function buildTree(string $dependency, array $path = []): void
    {
        $path[] = $dependency;
        foreach ($this->dependents[$dependency] as $dependent) {
            if (isset($this->relation[$dependency]) && $this->relation[$dependency] === $dependent) {
                // This relation was already reached on another path.
                NestedArray::setValue($this->tree, [...$path, $dependent], $dependent . ':' . self::CIRCULAR_REFERENCE);
                continue;
            }

            // Remember the relation to avoid infinite recursion.
            $this->relation[$dependency] = $dependent;

            if (isset($this->dependents[$dependent])) {
                $this->buildTree($dependent, $path);
            } else {
                NestedArray::setValue($this->tree, [...$path, $dependent], $dependent);
            }
        }
    }

    /**
     * Returns the module and config dependencies of every config entity.
     *
     * @return array{module: array<string, string[]>, config: array<string, string[]>}
     *   Dependencies keyed by type, then by config dependency name.
     */
    protected function scanConfigs(): array
    {
        $result = ['module' => [], 'config' => []];
        $configTypeIds = array_keys(array_filter(
            $this->entityTypeManager->getDefinitions(),
            fn(EntityTypeInterface $entityType): bool => $entityType->entityClassImplements(ConfigEntityInterface::class),
        ));
        foreach ($configTypeIds as $configTypeId) {
            /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $config */
            foreach ($this->entityTypeManager->getStorage($configTypeId)->loadMultiple() as $config) {
                $dependencies = $config->getDependencies();
                $name = $config->getConfigDependencyName();
                foreach (['module', 'config'] as $type) {
                    if (!empty($dependencies[$type])) {
                        $result[$type][$name] = $dependencies[$type];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Builds the tree under $root and writes it in the requested format.
     */
    protected function renderTree(OutputInterface $output, string $root, ?OutputFormat $format): void
    {
        $this->buildTree($root);
        $result = match ($format) {
            OutputFormat::Json => json_encode($this->tree, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => rtrim(Yaml::encode($this->tree), "\n"),
            null => $this->drawTree($root),
        };
        $output->writeln($result, $format === null ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW);
    }

    /**
     * Draws the tree with box-drawing characters.
     */
    private function drawTree(string $root): string
    {
        $iterator = new \RecursiveTreeIterator(
            new \RecursiveArrayIterator(current($this->tree)),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        $iterator->setPrefixPart(\RecursiveTreeIterator::PREFIX_END_HAS_NEXT, '├─');
        $iterator->setPrefixPart(\RecursiveTreeIterator::PREFIX_END_LAST, '└─');
        $iterator->setPrefixPart(\RecursiveTreeIterator::PREFIX_MID_HAS_NEXT, '│ ');
        $canvas = [$root];
        // The tree iterator's key is the prefixed label.
        for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
            $label = $iterator->key();
            $key = $iterator->getInnerIterator()->key();
            $current = $iterator->getInnerIterator()->current();
            if ($key . ':' . self::CIRCULAR_REFERENCE === $current) {
                $label .= ' <info>(' . $this->t('circular') . ')</info>';
            }
            $canvas[] = $label;
        }
        return implode(PHP_EOL, $canvas);
    }
}
