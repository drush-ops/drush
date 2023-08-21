<?php

declare(strict_types=1);

namespace Drush\Commands\field;

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
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function dt;
use function t;

class FieldCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use EntityTypeBundleAskTrait;
    use CustomEventAwareTrait;
    use EntityTypeBundleValidationTrait;

    const CREATE = 'field:create';

    protected ContentTranslationManagerInterface $contentTranslationManager;

    public function __construct(
        protected FieldTypePluginManagerInterface $fieldTypePluginManager,
        protected WidgetPluginManager $widgetPluginManager,
        protected SelectionPluginManagerInterface $selectionPluginManager,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        protected ModuleHandlerInterface $moduleHandler,
        protected EntityFieldManagerInterface $entityFieldManager
    ) {
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('plugin.manager.field.field_type'),
            $container->get('plugin.manager.field.widget'),
            $container->get('plugin.manager.entity_reference_selection'),
            $container->get('entity_type.manager'),
            $container->get('entity_type.bundle.info'),
            $container->get('module_handler'),
            $container->get('entity_field.manager')
        );

        if ($container->has('content_translation.manager')) {
            $commandHandler->setContentTranslationManager($container->get('content_translation.manager'));
        }

        return $commandHandler;
    }

    public function setContentTranslationManager(ContentTranslationManagerInterface $manager = null): void
    {
        if ($manager) {
            $this->contentTranslationManager = $manager;
        }
    }

    /**
     * Create a new field
     *
     * @see \Drupal\field_ui\Form\FieldConfigEditForm
     * @see \Drupal\field_ui\Form\FieldStorageConfigEditForm
     */
    #[CLI\Command(name: self::CREATE, aliases: ['field-create', 'fc'])]
    #[CLI\Argument(name: 'entityType', description: 'The machine name of the entity type')]
    #[CLI\Argument(name: 'bundle', description: 'The machine name of the bundle')]
    #[CLI\Option(name: 'field-name', description: 'A unique machine-readable name containing letters, numbers, and underscores.')]
    #[CLI\Option(name: 'field-label', description: 'The field label')]
    #[CLI\Option(name: 'field-description', description: 'Instructions to present to the user below this field on the editing form.')]
    #[CLI\Option(name: 'field-type', description: 'The field type')]
    #[CLI\Option(name: 'field-widget', description: 'The field widget')]
    #[CLI\Option(name: 'is-required', description: 'Whether the field is required')]
    #[CLI\Option(name: 'is-translatable', description: 'Whether the field is translatable')]
    #[CLI\Option(name: 'cardinality', description: 'The allowed number of values')]
    #[CLI\Option(name: 'target-type', description: 'The target entity type. Only necessary for entity reference fields.')]
    #[CLI\Option(name: 'target-bundle', description: 'The target bundle(s). Only necessary for entity reference fields.')]
    #[CLI\Option(name: 'existing', description: 'Re-use an existing field.')]
    #[CLI\Option(name: 'existing-field-name', description: 'The name of an existing field you want to re-use. Only used in non-interactive context.')]
    #[CLI\Option(name: 'show-machine-names', description: 'Show machine names instead of labels in option lists.')]
    #[CLI\Usage(name: self::CREATE, description: 'Create a field by answering the prompts.')]
    #[CLI\Usage(name: 'field-create taxonomy_term tag', description: 'Create a field and fill in the remaining information through prompts.')]
    #[CLI\Usage(name: 'field-create taxonomy_term tag --field-name=field_tag_label --field-label=Label --field-type=string --field-widget=string_textfield --is-required=1 --cardinality=2', description: 'Create a field in a completely non-interactive way.')]
    #[CLI\Complete(method_name_or_callable: 'complete')]
    #[CLI\Version(version: '11.0')]
    public function fieldCreate(?string $entityType = null, ?string $bundle = null, array $options = [
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
        'existing-field-name' => InputOption::VALUE_OPTIONAL,
        'existing' => false,
    ]): void
    {
        $this->input->setArgument('entityType', $entityType = $entityType ?? $this->askEntityType());
        $this->validateEntityType($entityType);

        $this->input->setArgument('bundle', $bundle = $bundle ?? $this->askBundle());
        $this->validateBundle($entityType, $bundle);

        if ($this->input->getOption('existing') || $this->input->getOption('existing-field-name')) {
            $this->ensureOption('existing-field-name', [$this, 'askExistingFieldName'], false);

            if (!$fieldName = $this->input->getOption('existing-field-name')) {
                throw new \InvalidArgumentException(
                    dt('There are no existing fields that can be added.')
                );
            }

            if (!$this->fieldStorageExists($fieldName, $entityType)) {
                throw new \InvalidArgumentException(
                    dt("Field storage with name '!fieldName' does not yet exist. Call this command without the --existing option first.", [
                        '!fieldName' => $fieldName,
                    ])
                );
            }

            $fieldStorage = $this->entityFieldManager->getFieldStorageDefinitions($entityType)[$fieldName];

            if ($this->fieldExists($fieldName, $entityType, $bundle)) {
                throw new \InvalidArgumentException(
                    dt("Field with name '!fieldName' already exists on bundle '!bundle'.", [
                        '!fieldName' => $fieldName,
                        '!bundle' => $bundle,
                    ])
                );
            }

            $this->input->setOption('field-name', $fieldName);
            $this->input->setOption('field-type', $fieldStorage->getType());
            $this->input->setOption('target-type', $fieldStorage->getSetting('target_type'));

            $this->ensureOption('field-label', [$this, 'askFieldLabel'], true);
            $this->ensureOption('field-description', [$this, 'askFieldDescription'], false);
            $this->ensureOption('field-widget', [$this, 'askFieldWidget'], false);
            $this->ensureOption('is-required', [$this, 'askRequired'], false);
            $this->ensureOption('is-translatable', [$this, 'askTranslatable'], false);
        } else {
            $this->ensureOption('field-label', [$this, 'askFieldLabel'], true);
            $this->ensureOption('field-name', [$this, 'askFieldName'], true);

            $fieldName = $this->input->getOption('field-name');
            if ($this->fieldStorageExists($fieldName, $entityType)) {
                throw new \InvalidArgumentException(
                    dt("Field storage with name '!fieldName' already exists. Call this command with the --existing option to add an existing field to a bundle.", [
                        '!fieldName' => $fieldName,
                    ])
                );
            }

            $this->ensureOption('field-description', [$this, 'askFieldDescription'], false);
            $this->ensureOption('field-type', [$this, 'askFieldType'], true);
            $this->ensureOption('field-widget', [$this, 'askFieldWidget'], false);
            $this->ensureOption('is-required', [$this, 'askRequired'], false);
            $this->ensureOption('is-translatable', [$this, 'askTranslatable'], false);
            $this->ensureOption('cardinality', [$this, 'askCardinality'], true);

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

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionType() === CompletionInput::TYPE_ARGUMENT_VALUE) {
            if ($input->getCompletionName() === 'entityType') {
                $suggestions->suggestValues(array_keys($this->getFieldableEntityTypes()));
            }

            if ($input->getCompletionName() === 'bundle') {
                $entityTypeId = $input->getArgument('entityType');
                $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);

                $suggestions->suggestValues(array_keys($bundleInfo));
            }
        }

        if ($input->getCompletionType() === CompletionInput::TYPE_OPTION_VALUE) {
            if ($input->getCompletionName() === 'existing-field-name') {
                $entityTypeId = $input->getArgument('entityType');
                $bundle = $input->getArgument('bundle');
                $showMachineNames = (bool) $input->getOption('show-machine-names');

                if ($entityTypeId && $bundle) {
                    $choices = $this->getExistingFieldStorageOptions($entityTypeId, $bundle, $showMachineNames);
                    $suggestions->suggestValues(array_keys($choices));
                }
            }

            if ($input->getCompletionName() === 'field-name') {
                $fieldLabel = $input->getOption('field-label');
                $bundle = $input->getArgument('bundle');

                if ($fieldLabel && $bundle) {
                    $suggestion = $this->generateFieldName($fieldLabel, $bundle);
                    $suggestions->suggestValue($suggestion);
                }
            }

            if ($input->getCompletionName() === 'field-type') {
                $fieldTypes = $this->fieldTypePluginManager->getDefinitions();
                $suggestions->suggestValues(array_keys($fieldTypes));
            }

            if ($input->getCompletionName() === 'field-widget') {
                $fieldType = $input->getOption('field-type');

                if ($fieldType) {
                    $fieldWidgets = $this->widgetPluginManager->getOptions($fieldType);
                    $suggestions->suggestValues(array_keys($fieldWidgets));
                }
            }
        }
    }

    protected function askExistingFieldName(): ?string
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $showMachineNames = $this->input->getOption('show-machine-names');
        $choices = $this->getExistingFieldStorageOptions($entityType, $bundle, $showMachineNames);

        if (empty($choices)) {
            return null;
        }

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
        return $this->io()->askRequired('Field label');
    }

    protected function askFieldDescription(): ?string
    {
        return $this->io()->ask('Field description');
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

    protected function askFieldWidget(): ?string
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

        if ($widgets === []) {
            $this->io()->comment('No widgets available for this field type. Skipping option.');
            return null;
        }

        foreach ($widgets as $name => $label) {
            $label = $this->input->getOption('show-machine-names') ? $name : $label->render();
            $choices[$name] = $label;
        }

        $default = $this->input->getOption('show-machine-names') ? key($choices) : current($choices);

        return $this->io()->choice('Field widget', $choices, $default);
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
            $limit = (int) $this->io()->ask('Allowed number of values', '1');
        }

        return $limit;
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

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('field-create-field-storage');
        foreach ($handlers as $handler) {
            $values = $handler($values, $this->input);
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
        $handlers = $this->getCustomEventHandlers(sprintf('field-create-%s-display', $context));
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $storage = $this->getEntityDisplay($context);

        if (!$storage instanceof EntityDisplayInterface) {
            $this->logger()->info(
                sprintf("'%s' display storage not found for %s type '%s', creating now.", $context, $entityType, $bundle)
            );

            $storage = $this->entityTypeManager
                ->getStorage(sprintf('entity_%s_display', $context))
                ->create([
                    'id' => sprintf('%s.%s.default', $entityType, $bundle),
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
            ->load(sprintf('%s.%s.default', $entityType, $bundle));
    }

    protected function logResult(FieldConfigInterface $field): void
    {
        $this->logger()->success(
            sprintf(
                "Successfully created field '%s' on %s type with bundle '%s'",
                $field->get('field_name'),
                $field->get('entity_type'),
                $field->get('bundle')
            )
        );

        /** @var EntityTypeInterface $entityType */
        $entityType = $this->entityTypeManager->getDefinition($field->get('entity_type'));

        $routeName = sprintf('entity.field_config.%s_field_edit_form', $entityType->id());
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

    protected function getExistingFieldStorageOptions(string $entityType, string $bundle, bool $showMachineNames): array
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
            $label = $showMachineNames
                ? $fieldTypes[$fieldType]['id']
                : $fieldTypes[$fieldType]['label'];

            if (
                $fieldStorage instanceof FieldStorageConfigInterface
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

    protected function ensureOption(string $name, callable $asker, bool $required): void
    {
        $value = $this->input->getOption($name);

        if ($value === null && $this->input->isInteractive()) {
            $value = $asker();
        }

        if ($required && $value === null) {
            throw new \InvalidArgumentException(dt('The !optionName option is required.', [
                '!optionName' => $name,
            ]));
        }

        $this->input->setOption($name, $value);
    }
}
