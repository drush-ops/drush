<?php

namespace Drush\Commands\field;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

final class FieldEntityReferenceHooks extends DrushCommands
{
    use AutowireTrait;
    use EntityTypeBundleValidationTrait;

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        protected EntityFieldManagerInterface $entityFieldManager,
    ) {
    }

    #[CLI\Hook(type: HookManager::ON_EVENT, target: 'field-create-field-storage')]
    public function hookFieldStorage(array $values, InputInterface $input): array
    {
        if ($input->getOption('field-type') === 'entity_reference') {
            $values['settings']['target_type'] = $this->getTargetType($input);
        }

        return $values;
    }

    #[CLI\Hook(type: HookManager::ON_EVENT, target: 'field-create-field-config')]
    public function hookFieldConfig(array $values, InputInterface $input): array
    {
        if ($input->getOption('field-type') === 'entity_reference') {
            $values['settings']['handler_settings']['target_bundles'] = $this->getTargetBundles($input);
        }

        return $values;
    }

    protected function getTargetType(InputInterface $input): string
    {
        $value = $input->getOption('target-type');

        if ($value === null && $input->isInteractive()) {
            $value = $this->askReferencedEntityType();
        }

        if ($value === null) {
            throw new \InvalidArgumentException(dt('The %optionName option is required.', [
                '%optionName' => 'target-type',
            ]));
        }

        $input->setOption('target-type', $value);

        return $value;
    }

    protected function getTargetBundles(InputInterface $input): ?array
    {
        $targetType = $input->getOption('target-type');
        $targetTypeDefinition = $this->entityTypeManager->getDefinition($targetType);
        // For the 'target_bundles' setting, a NULL value is equivalent to "allow
        // entities from any bundle to be referenced" and an empty array value is
        // equivalent to "no entities from any bundle can be referenced".
        $targetBundles = null;

        if ($targetTypeDefinition->hasKey('bundle')) {
            if ($referencedBundle = $input->getOption('target-bundle')) {
                $this->validateBundle($targetType, $referencedBundle);
                $referencedBundles = [$referencedBundle];
            } else {
                $referencedBundles = $this->askReferencedBundles($targetType);
            }

            if (!empty($referencedBundles)) {
                $targetBundles = array_combine($referencedBundles, $referencedBundles);
            }
        }

        return $targetBundles;
    }

    protected function askReferencedEntityType(): string
    {
        $definitions = $this->entityTypeManager->getDefinitions();
        $choices = [];

        foreach ($definitions as $name => $definition) {
            $label = $this->input->getOption('show-machine-names')
                ? $name
                : sprintf('%s: %s', $definition->getGroupLabel()->render(), $definition->getLabel());
            $choices[$name] = $label;
        }

        return $this->io()->select('Referenced entity type', $choices);
    }

    protected function askReferencedBundles(string $targetType): array
    {
        $choices = [];
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($targetType);

        if (empty($bundleInfo)) {
            return [];
        }

        foreach ($bundleInfo as $bundle => $info) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $info['label'];
            $choices[$bundle] = $label;
        }

        $default = $this->getExistingFieldForDefaults()?->getSetting('handler_settings')['target_bundles'] ?? [];

        return $this->io()->multiselect('Referenced bundles', $choices, $default);
    }

    protected function getExistingFieldForDefaults(): ?FieldDefinitionInterface
    {
        $existingBundle = $this->getExistingBundleForDefaults();
        if ($existingBundle === null) {
            return null;
        }

        $entityTypeId = $this->input->getArgument('entityType');
        $fieldName = $this->input->getOption('field-name');

        return $this->entityFieldManager->getFieldDefinitions($entityTypeId, $existingBundle)[$fieldName];
    }


    protected function getExistingBundleForDefaults(): ?string
    {
        $entityTypeId = $this->input->getArgument('entityType');
        $fieldName = $this->input->getOption('field-name');
        $fieldMap = $this->entityFieldManager->getFieldMap();

        if (empty($fieldMap[$entityTypeId][$fieldName]['bundles'])) {
            return null;
        }

        $bundles = $fieldMap[$entityTypeId][$fieldName]['bundles'];

        // Sort bundles to ensure deterministic behavior.
        sort($bundles);

        return reset($bundles);
    }
}
