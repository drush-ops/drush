<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Url;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

class FieldCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;

    /** @var FieldTypePluginManagerInterface */
    protected $fieldTypePluginManager;
    /** @var WidgetPluginManager */
    protected $widgetPluginManager;
    /** @var SelectionPluginManagerInterface */
    protected $selectionPluginManager;
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfoInterface */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManagerInterface */
    protected $entityFieldManager;
    /** @var ModuleHandlerInterface */
    protected $moduleHandler;
    /** @var ContentTranslationManagerInterface */
    protected $contentTranslationManager;

    public function __construct(
        FieldTypePluginManagerInterface $fieldTypePluginManager,
        WidgetPluginManager $widgetPluginManager,
        SelectionPluginManagerInterface $selectionPluginManager,
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        ModuleHandlerInterface $moduleHandler,
        EntityFieldManagerInterface $entityFieldManager
    ) {
        $this->fieldTypePluginManager = $fieldTypePluginManager;
        $this->widgetPluginManager = $widgetPluginManager;
        $this->selectionPluginManager = $selectionPluginManager;
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->moduleHandler = $moduleHandler;
        $this->entityFieldManager = $entityFieldManager;
    }

    public function setContentTranslationManager($manager): void
    {
        $this->contentTranslationManager = $manager;
    }

    /**
     * Create a new field
     *
     * @command field:create
     * @aliases field-create,fc
     *
     * @validate-entity-type-argument entityType
     * @validate-optional-bundle-argument entityType bundle
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     *
     * @option field-name
     *      A unique machine-readable name containing letters, numbers, and underscores.
     * @option field-label
     *      The field label
     * @option field-description
     *      Instructions to present to the user below this field on the editing form.
     * @option field-type
     *      The field type
     * @option field-widget
     *      The field widget
     * @option is-required
     *      Whether the field is required
     * @option is-translatable
     *      Whether the field is translatable
     * @option cardinality
     *      The allowed number of values
     * @option target-type
     *      The target entity type. Only necessary for entity reference fields.
     * @option target-bundle
     *      The target bundle(s). Only necessary for entity reference fields.
     *
     * @option existing
     *      Re-use an existing field.
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush field:create
     *      Create a field by answering the prompts.
     * @usage drush field-create taxonomy_term tag
     *      Create a field and fill in the remaining information through prompts.
     * @usage drush field-create taxonomy_term tag --field-name=field_tag_label --field-label=Label --field-type=string --field-widget=string_textfield --is-required=1 --cardinality=2
     *      Create a field in a completely non-interactive way.
     *
     * @see \Drupal\field_ui\Form\FieldConfigEditForm
     * @see \Drupal\field_ui\Form\FieldStorageConfigEditForm
     */
    public function create(string $entityType, ?string $bundle = null, array $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'field-label' => InputOption::VALUE_REQUIRED,
        'field-description' => InputOption::VALUE_OPTIONAL,
        'field-type' => InputOption::VALUE_REQUIRED,
        'field-widget' => InputOption::VALUE_REQUIRED,
        'is-required' => InputOption::VALUE_OPTIONAL,
        'is-translatable' => InputOption::VALUE_OPTIONAL,
        'cardinality' => InputOption::VALUE_REQUIRED,
        'target-type' => InputOption::VALUE_OPTIONAL,
        'target-bundle' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
        'existing' => false,
    ]): void
    {
        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
            );
        }

        $bundle = $this->ensureArgument('bundle', [$this, 'askBundle']);

        if (!$this->entityTypeBundleExists($entityType, $bundle)) {
            throw new \InvalidArgumentException(
                t('Bundle with id \':bundle\' does not exist on entity type \':entityType\'.', [
                    ':bundle' => $bundle,
                    ':entityType' => $entityType,
                ])
            );
        }

        if ($this->input->getOption('existing')) {
            $fieldName = $this->ensureOption('field-name', [$this, 'askExisting']);

            if (!$this->fieldStorageExists($fieldName, $entityType)) {
                throw new \InvalidArgumentException(
                    t('Field storage with name \':fieldName\' does not yet exist. Call this command without the --existing option first.', [
                        ':fieldName' => $fieldName,
                    ])
                );
            }

            $fieldStorage = $this->entityFieldManager->getFieldStorageDefinitions($entityType)[$fieldName];

            if ($this->fieldExists($fieldName, $entityType, $bundle)) {
                throw new \InvalidArgumentException(
                    t('Field with name \':fieldName\' already exists on bundle \':bundle\'.', [
                        ':fieldName' => $fieldName,
                        ':bundle' => $bundle,
                    ])
                );
            }

            $this->input->setOption('field-type', $fieldStorage->getType());
            $this->input->setOption('target-type', $fieldStorage->getSetting('target_type'));

            $this->ensureOption('field-label', [$this, 'askFieldLabel']);
            $this->ensureOption('field-description', [$this, 'askFieldDescription']);
            $this->ensureOption('field-widget', [$this, 'askFieldWidget']);
            $this->ensureOption('is-required', [$this, 'askRequired']);
            $this->ensureOption('is-translatable', [$this, 'askTranslatable']);
        } else {
            $this->ensureOption('field-label', [$this, 'askFieldLabel']);
            $fieldName = $this->ensureOption('field-name', [$this, 'askFieldName']);

            if ($this->fieldStorageExists($fieldName, $entityType)) {
                throw new \InvalidArgumentException(
                    t('Field storage with name \':fieldName\' already exists. Call this command with the --existing option to add an existing field to a bundle.', [
                        ':fieldName' => $fieldName,
                    ])
                );
            }

            $this->ensureOption('field-description', [$this, 'askFieldDescription']);
            $this->ensureOption('field-type', [$this, 'askFieldType']);
            $this->ensureOption('field-widget', [$this, 'askFieldWidget']);
            $this->ensureOption('is-required', [$this, 'askRequired']);
            $this->ensureOption('is-translatable', [$this, 'askTranslatable']);
            $this->ensureOption('cardinality', [$this, 'askCardinality']);

            if ($this->input->getOption('field-type') === 'entity_reference') {
                $this->ensureOption('target-type', [$this, 'askReferencedEntityType']);
            }

            $this->createFieldStorage();
        }

        // Command files may set additional options as desired.
        $handlers = $this->getCustomEventHandlers('field-create-set-options');
        foreach ($handlers as $handler) {
            $handler($this->input);
        }

        $field = $this->createField();
        $this->createFieldDisplay('form');
        $this->createFieldDisplay('view');

        $this->logResult($field);
    }

    protected function askExisting(): string
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $choices = $this->getExistingFieldStorageOptions($entityType, $bundle);

        return $this->io()->choice('Choose an existing field', $choices);
    }

    protected function askFieldName(): string
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldName = null;
        $machineName = null;

        if (!empty($fieldLabel)) {
            $machineName = $this->generateFieldName($fieldLabel, $bundle);
        }

        while (!$fieldName) {
            $answer = $this->io()->ask('Field name', $machineName);

            if (!preg_match('/^[_a-z]+[_a-z0-9]*$/', $answer)) {
                $this->logger()->error('Only lowercase alphanumeric characters and underscores are allowed, and only lowercase letters and underscore are allowed as the first character.');
                continue;
            }

            if (strlen($answer) > 32) {
                $this->logger()->error('Field name must not be longer than 32 characters.');
                continue;
            }

            if ($this->fieldStorageExists($answer, $entityType)) {
                $this->logger()->error('A field with this name already exists.');
                continue;
            }

            $fieldName = $answer;
        }

        return $fieldName;
    }

    protected function askFieldLabel(): string
    {
        return $this->io()->ask('Field label');
    }

    protected function askFieldDescription(): ?string
    {
        return $this->optionalAsk('Field description');
    }

    protected function askFieldType(): string
    {
        $definitions = $this->fieldTypePluginManager->getDefinitions();
        $choices = [];

        foreach ($definitions as $definition) {
            $label = $this->input->getOption('show-machine-names') ? $definition['id'] : $definition['label']->render();
            $choices[$definition['id']] = $label;
        }

        return $this->io()->choice('Field type', $choices);
    }

    protected function askFieldWidget(): string
    {
        $formDisplay = $this->getEntityDisplay('form');

        if ($formDisplay instanceof EntityFormDisplayInterface) {
            $component = $formDisplay->getComponent($this->input->getOption('field-name'));

            if (isset($component['type'])) {
                return $component['type'];
            }
        }

        $choices = [];
        $fieldType = $this->input->getOption('field-type');
        $widgets = $this->widgetPluginManager->getOptions($fieldType);

        foreach ($widgets as $name => $label) {
            $label = $this->input->getOption('show-machine-names') ? $name : $label->render();
            $choices[$name] = $label;
        }

        return $this->io()->choice('Field widget', $choices, 0);
    }

    protected function askRequired(): bool
    {
        return $this->io()->confirm('Required', false);
    }

    protected function askTranslatable(): bool
    {
        if (!$this->hasContentTranslation()) {
            return false;
        }

        return $this->io()->confirm('Translatable', false);
    }

    protected function askBundle(): ?string
    {
        $entityTypeId = $this->input->getArgument('entityType');
        $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId);
        $bundleEntityType = $entityTypeDefinition->getBundleEntityType();
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
        $choices = [];

        if (empty($bundleInfo)) {
            if ($bundleEntityType) {
                throw new \InvalidArgumentException(
                    t('Entity type with id \':entityType\' does not have any bundles.', [':entityType' => $entityTypeId])
                );
            }

            return null;
        }

        foreach ($bundleInfo as $bundle => $data) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $data['label'];
            $choices[$bundle] = $label;
        }

        if (!$answer = $this->io()->choice('Bundle', $choices)) {
            throw new \InvalidArgumentException(t('The bundle argument is required.'));
        }

        return $answer;
    }

    protected function askCardinality(): int
    {
        $fieldType = $this->input->getOption('field-type');
        $definition = $this->fieldTypePluginManager->getDefinition($fieldType);

        // Some field types choose to enforce a fixed cardinality.
        if (isset($definition['cardinality'])) {
            return $definition['cardinality'];
        }

        $choices = ['Limited', 'Unlimited'];
        $cardinality = $this->io()->choice(
            'Allowed number of values',
            array_combine($choices, $choices),
            0
        );

        $limit = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
        while ($cardinality === 'Limited' && $limit < 1) {
            $limit = (int) $this->io()->ask('Allowed number of values', 1);
        }

        return $limit;
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

        return $this->io()->choice('Referenced entity type', $choices);
    }

    protected function askReferencedBundles(FieldDefinitionInterface $fieldDefinition): array
    {
        $choices = [];
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo(
            $fieldDefinition->getFieldStorageDefinition()->getSetting('target_type')
        );

        if (empty($bundleInfo)) {
            return [];
        }

        foreach ($bundleInfo as $bundle => $info) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $info['label'];
            $choices[$bundle] = $label;
        }

        return $this->io()->choice('Referenced bundles', $choices, 0);
    }

    protected function createField(): FieldConfigInterface
    {
        $values = [
            'field_name' => $this->input->getOption('field-name'),
            'entity_type' => $this->input->getArgument('entityType'),
            'bundle' => $this->input->getArgument('bundle'),
            'translatable' => $this->input->getOption('is-translatable'),
            'required' => $this->input->getOption('is-required'),
            'field_type' => $this->input->getOption('field-type'),
            'description' => $this->input->getOption('field-description') ?? '',
            'label' => $this->input->getOption('field-label'),
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('field-create-field-config');
        foreach ($handlers as $handler) {
            $values = $handler($values, $this->input);
        }

        $field = $this->entityTypeManager
            ->getStorage('field_config')
            ->create($values);

        if ($this->input->getOption('field-type') === 'entity_reference') {
            $targetType = $this->input->getOption('target-type');
            $targetTypeDefinition = $this->entityTypeManager->getDefinition($targetType);
            // For the 'target_bundles' setting, a NULL value is equivalent to "allow
            // entities from any bundle to be referenced" and an empty array value is
            // equivalent to "no entities from any bundle can be referenced".
            $targetBundles = null;

            if ($targetTypeDefinition->hasKey('bundle')) {
                if ($referencedBundle = $this->input->getOption('target-bundle')) {
                    $referencedBundles = [$referencedBundle];
                } else {
                    $referencedBundles = $this->askReferencedBundles($field);
                }

                if (!empty($referencedBundles)) {
                    $targetBundles = array_combine($referencedBundles, $referencedBundles);
                }
            }

            $settings = $field->getSetting('handler_settings') ?? [];
            $settings['target_bundles'] = $targetBundles;
            $field->setSetting('handler_settings', $settings);
        }

        $field->save();

        return $field;
    }

    protected function createFieldStorage(): FieldStorageConfigInterface
    {
        $values = [
            'field_name' => $this->input->getOption('field-name'),
            'entity_type' => $this->input->getArgument('entityType'),
            'type' => $this->input->getOption('field-type'),
            'cardinality' => $this->input->getOption('cardinality'),
            'translatable' => true,
        ];

        if ($targetType = $this->input->getOption('target-type')) {
            $values['settings']['target_type'] = $targetType;
        }

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('field-create-field-storage');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        /** @var FieldStorageConfigInterface $fieldStorage */
        $fieldStorage = $this->entityTypeManager
            ->getStorage('field_storage_config')
            ->create($values);

        $fieldStorage->save();

        return $fieldStorage;
    }

    protected function createFieldDisplay(string $context): void
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $fieldName = $this->input->getOption('field-name');
        $fieldWidget = $this->input->getOption('field-widget');
        $values = [];

        if ($fieldWidget && $context === 'form') {
            $values['type'] = $fieldWidget;
        }

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers("field-create-{$context}-display");
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $storage = $this->getEntityDisplay($context);

        if (!$storage instanceof EntityDisplayInterface) {
            $this->logger()->info(
                sprintf('\'%s\' display storage not found for %s type \'%s\', creating now.', $context, $entityType, $bundle)
            );

            $storage = $this->entityTypeManager
                ->getStorage(sprintf('entity_%s_display', $context))
                ->create([
                    'id' => "$entityType.$bundle.default",
                    'targetEntityType' => $entityType,
                    'bundle' => $bundle,
                    'mode' => 'default',
                    'status' => true,
                ]);

            $storage->save();
        }

        $storage->setComponent($fieldName, $values)->save();
    }

    protected function getEntityDisplay(string $context): ?EntityDisplayInterface
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        return $this->entityTypeManager
            ->getStorage(sprintf('entity_%s_display', $context))
            ->load("$entityType.$bundle.default");
    }

    protected function logResult(FieldConfigInterface $field): void
    {
        $this->logger()->success(
            sprintf(
                'Successfully created field \'%s\' on %s type with bundle \'%s\'',
                $field->get('field_name'),
                $field->get('entity_type'),
                $field->get('bundle')
            )
        );

        /** @var EntityTypeInterface $entityType */
        $entityType = $this->entityTypeManager->getDefinition($field->get('entity_type'));

        $routeName = "entity.field_config.{$entityType->id()}_field_edit_form";
        $routeParams = [
            'field_config' => $field->id(),
            $entityType->getBundleEntityType() => $field->get('bundle'),
        ];

        if ($this->moduleHandler->moduleExists('field_ui')) {
            $this->logger()->success(
                'Further customisation can be done at the following url:'
                . PHP_EOL
                . Url::fromRoute($routeName, $routeParams)
                    ->setAbsolute(true)
                    ->toString()
            );
        }
    }

    protected function generateFieldName(string $source, string $bundle): string
    {
        // Only lowercase alphanumeric characters and underscores
        $machineName = preg_replace('/[^_a-z0-9]/i', '_', $source);
        // Only lowercase letters and underscores as the first character
        $machineName = preg_replace('/^[^_a-z]/i', '_', $machineName);
        // Maximum one subsequent underscore
        $machineName = preg_replace('/_+/', '_', $machineName);
        // Only lowercase
        $machineName = strtolower($machineName);
        // Add the prefix
        $machineName = sprintf('field_%s_%s', $bundle, $machineName);
        // Maximum 32 characters
        $machineName = substr($machineName, 0, 32);

        return $machineName;
    }

    protected function fieldExists(string $fieldName, string $entityType, string $bundle): bool
    {
        $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);

        return isset($fieldDefinitions[$fieldName]);
    }

    protected function fieldStorageExists(string $fieldName, string $entityType): bool
    {
        $fieldStorageDefinitions = $this->entityFieldManager->getFieldStorageDefinitions($entityType);

        return isset($fieldStorageDefinitions[$fieldName]);
    }

    protected function entityTypeBundleExists(string $entityType, string $bundleName): bool
    {
        return isset($this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundleName]);
    }

    protected function getExistingFieldStorageOptions(string $entityType, string $bundle): array
    {
        $fieldTypes = $this->fieldTypePluginManager->getDefinitions();
        $options = [];

        foreach ($this->entityFieldManager->getFieldStorageDefinitions($entityType) as $fieldName => $fieldStorage) {
            // Do not show:
            // - non-configurable field storages,
            // - locked field storages,
            // - field storages that should not be added via user interface,
            // - field storages that already have a field in the bundle.
            $fieldType = $fieldStorage->getType();
            $label = $this->input->getOption('show-machine-names')
                ? $fieldTypes[$fieldType]['id']
                : $fieldTypes[$fieldType]['label'];

            if ($fieldStorage instanceof FieldStorageConfigInterface
                && !$fieldStorage->isLocked()
                && empty($fieldTypes[$fieldType]['no_ui'])
                && !in_array($bundle, $fieldStorage->getBundles(), true)
            ) {
                $options[$fieldName] = sprintf('%s (%s)', $fieldName, $label);
            }
        }

        asort($options);

        return $options;
    }

    protected function hasContentTranslation(): bool
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        return $this->moduleHandler->moduleExists('content_translation')
            && $this->contentTranslationManager->isEnabled($entityType, $bundle);
    }

    protected function ensureArgument(string $name, callable $asker)
    {
        $value = $this->input->getArgument($name) ?? $asker();
        $this->input->setArgument($name, $value);

        return $value;
    }

    protected function ensureOption(string $name, callable $asker)
    {
        $value = $this->input->getOption($name) ?? $asker();
        $this->input->setOption($name, $value);

        return $value;
    }

    protected function optionalAsk(string $question)
    {
        return $this->io()->ask($question, null, function ($value) {
            return $value;
        });
    }
}
