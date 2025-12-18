<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\field\EntityTypeBundleValidationTrait;
use Drush\Commands\field\FieldCreateCommand;
use Drush\Commands\IoTrait;
use Drush\Event\ConsoleDefinitionsEvent;
use Drush\Event\FieldCreateFieldConfigValuesEvent;
use Drush\Event\FieldCreateFieldStorageConfigValuesEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(method: 'onConsoleDefinitionEvent')]
#[AsEventListener(method: 'onFieldConfigValues')]
#[AsEventListener(method: 'onFieldStorageConfigValues')]
final class CreateEntityReferenceFieldListener
{
    use AutowireTrait;
    use EntityTypeBundleValidationTrait;
    use IoTrait;

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        protected EntityFieldManagerInterface $entityFieldManager,
    ) {
    }

    public function onConsoleDefinitionEvent(ConsoleDefinitionsEvent $event): void
    {
        $application = $event->getApplication();
        if (!$application->has(FieldCreateCommand::NAME)) {
            return;
        }

        $command = $application->get(FieldCreateCommand::NAME);

        $command->addOption(
            'target-type',
            '',
            InputOption::VALUE_OPTIONAL,
            'The target entity type for the entity reference field.',
        );

        $command->addOption(
            'target-bundle',
            '',
            InputOption::VALUE_OPTIONAL,
            'The target bundle for the entity reference field.',
        );
    }

    public function onFieldConfigValues(FieldCreateFieldConfigValuesEvent $event): void
    {
        $this->setIo($event->getInput(), $event->getOutput());
        $values = $event->getValues();

        if ($this->input->getOption('field-type') === 'entity_reference') {
            $values['settings']['handler_settings']['target_bundles'] = $this->getTargetBundles($this->input);
        }

        $event->setValues($values);
    }

    public function onFieldStorageConfigValues(FieldCreateFieldStorageConfigValuesEvent $event): void
    {
        $this->setIo($event->getInput(), $event->getOutput());
        $values = $event->getValues();

        if ($this->input->getOption('field-type') === 'entity_reference') {
            $values['settings']['target_type'] = $this->getTargetType($this->input);
        }

        $event->setValues($values);
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
        $targetType = $this->input->getOption('target-type');
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

            if ($referencedBundles !== []) {
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
