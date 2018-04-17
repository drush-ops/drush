<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class FieldCreateCommands extends DrushCommands
{
    /** @var FieldTypePluginManager */
    protected $fieldTypePluginManager;
    /** @var WidgetPluginManager */
    protected $widgetPluginManager;
    /** @var SelectionPluginManager */
    protected $selectionPluginManager;
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var ModuleHandler */
    protected $moduleHandler;

    public function __construct(
        FieldTypePluginManager $fieldTypePluginManager,
        WidgetPluginManager $widgetPluginManager,
        SelectionPluginManager $selectionPluginManager,
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ModuleHandler $moduleHandler,
        EntityFieldManager $entityFieldManager
    ) {
        $this->fieldTypePluginManager = $fieldTypePluginManager;
        $this->widgetPluginManager = $widgetPluginManager;
        $this->selectionPluginManager = $selectionPluginManager;
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->moduleHandler = $moduleHandler;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * Create a new field
     *
     * @command field:create
     * @aliases field-create,fc
     *
     * @param string $entityType
     *      Name of bundle to attach fields to.
     * @param string $bundle
     *      Type of entity (e.g. node, user, comment).
     *
     * @option field-name
     * @option field-label
     * @option field-type
     * @option field-widget
     * @option is-required
     * @option cardinality
     * @option target-type
     *      Only necessary for entity reference fields.
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
     */
    public function create($entityType, $bundle, $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'field-label' => InputOption::VALUE_REQUIRED,
        'field-type' => InputOption::VALUE_REQUIRED,
        'field-widget' => InputOption::VALUE_REQUIRED,
        'is-required' => InputOption::VALUE_REQUIRED,
        'cardinality' => InputOption::VALUE_REQUIRED,
        'target-type' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
        'existing' => false,
    ])
    {
        $fieldName = $this->input->getOption('field-name');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldType = $this->input->getOption('field-type');
        $fieldWidget = $this->input->getOption('field-widget');
        $isRequired = $this->input->getOption('is-required');
        $cardinality = $this->input->getOption('cardinality');
        $targetType = $this->input->getOption('target-type');

        if (!$options['existing']) {
            $this->createFieldStorage($fieldName, $fieldType, $entityType, $targetType, $cardinality);
        }

        $field = $this->createField($fieldName, $fieldLabel, $entityType, $bundle, $isRequired);
        $this->createFieldFormDisplay($fieldName, $fieldWidget, $entityType, $bundle);
        $this->createFieldViewDisplay($fieldName, $entityType, $bundle);

        $this->logResult($field);
    }

    /**
     * @hook interact field:create
     */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        if (empty($bundle) || !$this->entityTypeBundleExists($entityType, $bundle)) {
            $this->input->setArgument('bundle', $this->askBundle());
        }

        if ($this->input->getOption('existing')) {
            $this->input->setOption(
                'field-name',
                $this->input->getOption('field-name') ?? $this->askExisting()
            );
            $this->input->setOption(
                'field-label',
                $this->input->getOption('field-label') ?? $this->askFieldLabel()
            );
            $this->input->setOption(
                'is-required',
                $this->input->getOption('is-required') ?? $this->askRequired()
            );

            /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $formDisplay */
            $formDisplay = $this->entityTypeManager
                ->getStorage('entity_form_display')
                ->load("$entityType.$bundle.default");

            if (!$formDisplay || $this->input->getOption('field-widget')) {
                return;
            }

            $component = $formDisplay->getComponent($this->input->getOption('field-name'));
            $this->input->setOption('field-widget', $component['type']);
        } else {
            $this->input->setOption(
                'field-label',
                $this->input->getOption('field-label') ?? $this->askFieldLabel()
            );
            $this->input->setOption(
                'field-name',
                $this->input->getOption('field-name') ?? $this->askFieldName()
            );
            $this->input->setOption(
                'field-type',
                $this->input->getOption('field-type') ?? $this->askFieldType()
            );
            $this->input->setOption(
                'field-widget',
                $this->input->getOption('field-widget') ?? $this->askFieldWidget()
            );
            $this->input->setOption(
                'is-required',
                (bool) ($this->input->getOption('is-required') ?? $this->askRequired())
            );
            $this->input->setOption(
                'cardinality',
                $this->input->getOption('cardinality') ?? $this->askCardinality()
            );

            if ($this->input->getOption('field-type') === 'entity_reference'
                && !$this->input->getOption('target-type')
            ) {
                $this->input->setOption('target-type', $this->askReferencedEntityType());
            }
        }
    }

    /**
     * @hook validate field:create
     */
    public function validateEntityType(CommandData $commandData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
            );
        }
    }

    protected function askExisting()
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $choices = $this->getExistingFieldStorageOptions($entityType, $bundle);
        return $this->choice('Choose an existing field', $choices);
    }

    protected function askBundle()
    {
        $entityType = $this->input->getArgument('entityType');
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityType);
        $choices = [];

        foreach ($bundleInfo as $bundle => $data) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $data['label'];
            $choices[$bundle] = $label;
        }

        return $this->choice('Bundle', $choices);
    }

    protected function askFieldName()
    {
        $entityType = $this->input->getArgument('entityType');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldName = null;
        $machineName = null;

        if (!empty($fieldLabel)) {
            $machineName = $this->generateFieldName($fieldLabel);
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

    protected function askFieldLabel()
    {
        return $this->io()->ask('Field label');
    }

    protected function askFieldType()
    {
        $definitions = $this->fieldTypePluginManager->getDefinitions();
        $choices = [];

        foreach ($definitions as $definition) {
            $label = $this->input->getOption('show-machine-names') ? $definition['id'] : $definition['label']->render();
            $choices[$definition['id']] = $label;
        }

        return $this->choice('Field type', $choices);
    }

    protected function askFieldWidget()
    {
        $choices = [];
        $fieldType = $this->input->getOption('field-type');
        $widgets = $this->widgetPluginManager->getOptions($fieldType);

        foreach ($widgets as $name => $label) {
            $label = $this->input->getOption('show-machine-names') ? $name : $label->render();
            $choices[$name] = $label;
        }

        return $this->choice('Field widget', $choices, false, 0);
    }

    protected function askRequired()
    {
        return $this->io()->askQuestion(new ConfirmationQuestion('Required', false));
    }

    protected function askCardinality()
    {
        $fieldType = $this->input->getOption('field-type');
        $enforcedCardinality = $this->getEnforcedCardinality($fieldType);

        if (!is_null($enforcedCardinality)) {
            return $enforcedCardinality;
        }

        $choices = ['Limited', 'Unlimited'];
        $cardinality = $this->choice(
            'Allowed number of values',
            array_combine($choices, $choices),
            false,
            0
        );

        $limit = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
        while ($cardinality === 'Limited' && $limit < 1) {
            $limit = $this->io()->ask('Allowed number of values', 1);
        }

        return (int) $limit;
    }

    protected function askReferencedEntityType()
    {
        $definitions = $this->entityTypeManager->getDefinitions();
        $choices = [];

        /** @var \Drupal\Core\Config\Entity\ConfigEntityType $definition */
        foreach ($definitions as $name => $definition) {
            $label = $this->input->getOption('show-machine-names')
                ? $name
                : sprintf('%s: %s', $definition->getGroupLabel()->render(), $definition->getLabel());
            $choices[$name] = $label;
        }

        return $this->choice('Referenced entity type', $choices);
    }

    protected function askReferencedBundles(FieldDefinitionInterface $fieldDefinition)
    {
        $choices = [];
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo(
            $fieldDefinition->getFieldStorageDefinition()->getSetting('target_type')
        );

        if (empty($bundleInfo)) {
            return null;
        }

        foreach ($bundleInfo as $bundle => $info) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $info['label'];
            $choices[$bundle] = $label;
        }

        $answers = $this->choice('Referenced bundles', $choices, true, 0);

        return [
            'target_bundles' => array_combine($answers, $answers),
            'sort' => [
                'field' => '_none',
                'direction' => 'ASC',
            ],
            'auto_create' => false,
            'auto_create_bundle' => null,
        ];
    }

    protected function createField(string $fieldName, $fieldLabel, string $entityType, string $bundle, bool $isRequired)
    {
        $values = [
            'field_name' => $fieldName,
            'entity_type' => $entityType,
            'bundle' => $bundle,
            'translatable' => false,
            'required' => $isRequired,
        ];

        if (!empty($fieldLabel)) {
            $values['label'] = $fieldLabel;
        }

        /** @var FieldConfig $field */
        $field = $this->entityTypeManager
            ->getStorage('field_config')
            ->create($values);

        $field->save();

        $fieldType = $this->getFieldType($fieldName, $entityType, $bundle);
        if ($fieldType instanceof EntityReferenceItem && $handlerSettings = $this->askReferencedBundles($field)) {
            $field->setSetting('handler_settings', $handlerSettings);
            $field->save();
        }

        return $field;
    }

    protected function createFieldStorage(string $fieldName, string $fieldType, string $entityType, $targetType, int $cardinality)
    {
        $values = [
            'field_name' => $fieldName,
            'entity_type' => $entityType,
            'type' => $fieldType,
            'cardinality' => $cardinality,
            // 'translatable' => false,
        ];

        if ($targetType) {
            $values['settings']['target_type'] = $targetType;
        }

        /** @var FieldStorageConfigInterface $fieldStorage */
        $fieldStorage = $this->entityTypeManager
            ->getStorage('field_storage_config')
            ->create($values);

        $fieldStorage->save();

        return $fieldStorage;
    }

    protected function createFieldFormDisplay(string $fieldName, $fieldWidget, string $entityType, string $bundle)
    {
        $values = [];

        if ($fieldWidget) {
            $values['type'] = $fieldWidget;
        }

        $storage = $this->entityTypeManager
            ->getStorage('entity_form_display')
            ->load("$entityType.$bundle.default");

        if (empty($storage)) {
            $this->logger()->info(
                sprintf('Form display storage not found for %s type \'%s\', creating now.', $entityType, $bundle)
            );

            $storage = $this->createDisplayStorage('form', $entityType, $bundle);
        }

        $storage->setComponent($fieldName, $values)->save();
    }

    protected function createFieldViewDisplay(string $fieldName, string $entityType, string $bundle)
    {
        $values = [];

        $storage = $this->entityTypeManager
            ->getStorage('entity_view_display')
            ->load("$entityType.$bundle.default");

        if (empty($storage)) {
            $this->logger()->info(
                sprintf('View display storage not found for %s type \'%s\', creating now.', $entityType, $bundle)
            );

            $storage = $this->createDisplayStorage('view', $entityType, $bundle);
        }

        $storage->setComponent($fieldName, $values)->save();
    }

    protected function createDisplayStorage(string $context, string $entityType, string $bundle)
    {
        $storageValues = [
            'id' => "$entityType.$bundle.default",
            'targetEntityType' => $entityType,
            'bundle' => $bundle,
            'mode' => 'default',
            'status' => true,
        ];

        $storage = $this->entityTypeManager
            ->getStorage(sprintf('entity_%s_display', $context))
            ->create($storageValues);

        $storage->save();

        return $storage;
    }

    protected function logResult(FieldConfig $field)
    {
        $this->logger()->success(
            sprintf(
                'Successfully created field \'%s\' on %s type with bundle \'%s\'',
                $field->get('field_name'),
                $field->get('entity_type'),
                $field->get('bundle')
            )
        );

        $routeName = "entity.field_config.{$field->get('entity_type')}_field_edit_form";
        $routeParams = [
            'field_config' => $field->id(),
            "{$field->get('entity_type')}_type" => $field->get('bundle'),
        ];

        if ($this->input->getArgument('entityType') === 'taxonomy_term') {
            $routeParams['taxonomy_vocabulary'] = $field->get('bundle');
        }

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

    protected function generateFieldName(string $source)
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
        $machineName = sprintf('field_%s', $machineName);
        // Maximum 32 characters
        $machineName = substr($machineName, 0, 32);

        return $machineName;
    }

    protected function fieldStorageExists(string $fieldName, string $entityType)
    {
        $fieldStorageDefinitions = $this->entityFieldManager->getFieldStorageDefinitions($entityType);
        return isset($fieldStorageDefinitions[$fieldName]);
    }

    protected function entityTypeBundleExists(string $entityType, string $bundleName)
    {
        return isset($this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundleName]);
    }

    protected function getExistingFieldStorageOptions(string $entityType, string $bundle)
    {
        $options = [];

        // Load the fieldStorages and build the list of options.
        $fieldTypes = $this->fieldTypePluginManager->getDefinitions();

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

    /**
     * Returns the cardinality enforced by the field type.
     *
     * Some field types choose to enforce a fixed cardinality. This method
     * returns that cardinality or NULL if no cardinality has been enforced.
     *
     * @param string $entityType
     * @return int|null
     */
    protected function getEnforcedCardinality(string $entityType)
    {
        $definition = $this->fieldTypePluginManager->getDefinition($entityType);
        return $definition['cardinality'] ?? null;
    }

    /**
     * @param string $fieldName
     * @param string $entityType
     * @param string $bundle
     * @return \Drupal\Core\Field\FieldItemInterface
     */
    protected function getFieldType(string $fieldName, string $entityType, string $bundle)
    {
        $ids = (object) [
            'entity_type' => $entityType,
            'bundle' => $bundle,
            'entity_id' => null,
        ];

        $entity = _field_create_entity_from_ids($ids);
        $items = $entity->get($fieldName);
        $item = $items->first() ?: $items->appendItem();

        return $item;
    }

    /**
     * @param string $question
     * @param array $choices
     *   If an associative array is passed, the chosen *key* is returned.
     * @param bool $multiSelect
     * @param null $default
     * @return mixed
     */
    protected function choice($question, array $choices, $multiSelect = false, $default = null)
    {
        $choicesValues = array_values($choices);
        $question = new ChoiceQuestion($question, $choicesValues, $default);
        $question->setMultiselect($multiSelect);
        $return = $this->io()->askQuestion($question);

        if ($multiSelect) {
            return array_map(
                function ($value) use ($choices) {
                    return array_search($value, $choices);
                },
                $return
            );
        }

        return array_search($return, $choices);
    }
}
