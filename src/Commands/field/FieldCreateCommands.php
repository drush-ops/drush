<?php

declare(strict_types=1);

namespace Drush\Commands\field;

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
use Drush\Commands\AutowireTrait;
use Drush\Commands\IoTrait;
use Drush\Event\FieldCreateEntityDisplayValuesEvent;
use Drush\Event\FieldCreateFieldConfigValuesEvent;
use Drush\Event\FieldCreateFieldStorageConfigValuesEvent;
use Drush\Event\FieldCreateInputOptionsEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function dt;

/**
 * Create a new field.
 *
 * @see \Drupal\field_ui\Form\FieldConfigEditForm
 * @see \Drupal\field_ui\Form\FieldStorageConfigEditForm
 */
#[AsCommand(
    name: self::CREATE,
    description: 'Create a new field.',
    aliases: ['field-create', 'fc'],
)]
#[CLI\Version(version: '11.0')]
final class FieldCreateCommands extends Command
{
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;
    use AutowireTrait;
    use IoTrait;

    const CREATE = 'field:create';

    public function __construct(
        private readonly FieldTypePluginManagerInterface $fieldTypePluginManager,
        #[Autowire(service: 'plugin.manager.field.widget')]
        private readonly WidgetPluginManager $widgetPluginManager,
        private readonly SelectionPluginManagerInterface $selectionPluginManager,
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        private readonly ModuleHandlerInterface $moduleHandler,
        private readonly EntityFieldManagerInterface $entityFieldManager,
        private readonly LoggerInterface $logger,
        private readonly ?ContentTranslationManagerInterface $contentTranslationManager = null,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'entityType', mode: InputArgument::OPTIONAL, description: 'The machine name of the entity type')
            ->addArgument(name: 'bundle', mode: InputArgument::OPTIONAL, description: 'The machine name of the bundle')
            ->addOption(name: 'field-name', mode: InputOption::VALUE_REQUIRED, description: 'A unique machine-readable name containing letters, numbers, and underscores')
            ->addOption(name: 'field-label', mode: InputOption::VALUE_REQUIRED, description: 'The field label')
            ->addOption(name: 'field-description', mode: InputOption::VALUE_OPTIONAL, description: 'Instructions to present to the user below this field on the editing form')
            ->addOption(name: 'field-type', mode: InputOption::VALUE_REQUIRED, description: 'The field type')
            ->addOption(name: 'field-widget', mode: InputOption::VALUE_REQUIRED, description: 'The field widget')
            ->addOption(name: 'is-required', mode: InputOption::VALUE_OPTIONAL, description: 'Whether the field is required')
            ->addOption(name: 'is-translatable', mode: InputOption::VALUE_OPTIONAL, description: 'Whether the field is translatable')
            ->addOption(name: 'cardinality', mode: InputOption::VALUE_REQUIRED, description: 'The allowed number of values')
            ->addOption(name: 'target-type', mode: InputOption::VALUE_OPTIONAL, description: 'The target entity type. Only necessary for entity reference fields.')
            ->addOption(name: 'target-bundle', mode: InputOption::VALUE_OPTIONAL, description: 'The target bundle(s). Only necessary for entity reference fields.')
            ->addOption(name: 'existing', mode: InputOption::VALUE_OPTIONAL, description: 'Re-use an existing field.')
            ->addOption(name: 'existing-field-name', mode: InputOption::VALUE_OPTIONAL, description: 'The name of an existing field you want to re-use. Only used in non-interactive context.')
            ->addOption(name: 'show-machine-names', mode: InputOption::VALUE_OPTIONAL, description: 'Show machine names instead of labels in option lists')
            ->addUsage('field:create taxonomy_term tag')
            ->addUsage('field:create taxonomy_term tag --field-name=field_tag_label --field-label=Label --field-type=string --field-widget=string_textfield --is-required=1 --cardinality=2')
            ->addUsage('field:create node article --field-name=field_article_summary --field-label=Summary --field-type=text_long --allowed-formats=full_html --allowed-formats=basic_html');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setIo($input, $output);

        $entityType = $input->getArgument('entityType') ?? $this->askEntityType();
        $this->input->setArgument('entityType', $entityType);
        $this->validateEntityType($entityType);

        $bundle = $input->getArgument('bundle') ?? $this->askBundle();
        $this->input->setArgument('bundle', $bundle);
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

        // Event subscribers may set additional options as desired.
        $event = new FieldCreateInputOptionsEvent($this->input);
        $this->eventDispatcher->dispatch($event);

        $field = $this->createField();
        $this->createFieldDisplay('form');
        $this->createFieldDisplay('view');

        $this->logResult($field);

        return Command::SUCCESS;
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
        $showMachineNames = (bool) $this->input->getOption('show-machine-names');
        $choices = $this->getExistingFieldStorageOptions($entityType, $bundle, $showMachineNames);

        if ($choices === []) {
            return null;
        }

        return $this->io()->select('Choose an existing field', $choices);
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
            $answer = $this->io()->ask('Field name', default: $machineName, required: true, validate: function ($answer) use ($entityType) {
                if (!preg_match('/^[_a-z]+[_a-z0-9]*$/', $answer)) {
                    return'Only lowercase alphanumeric chars/underscores allowed; only letters/underscore allowed as first character.';
                }

                if (strlen($answer) > 32) {
                    return 'Field name must not be longer than 32 characters.';
                }

                if ($this->fieldStorageExists($answer, $entityType)) {
                    return 'A field with this name already exists.';
                }
            });

            $fieldName = $answer;
        }

        return $fieldName;
    }

    protected function askFieldLabel(): string
    {
        $default = $this->getExistingFieldForDefaults()?->getLabel();
        return $this->io()->ask('Field label', $default, required: true);
    }

    protected function askFieldDescription(): ?string
    {
        $default = $this->getExistingFieldForDefaults()?->getDescription();
        return $this->io()->ask('Field description', $default);
    }

    protected function askFieldType(): string
    {
        $definitions = $this->fieldTypePluginManager->getDefinitions();
        $choices = [];

        foreach ($definitions as $definition) {
            if (isset($definition['no_ui']) && $definition['no_ui'] === true) {
                continue;
            }

            $label = $this->input->getOption('show-machine-names') ? $definition['id'] : $definition['label']->render();
            $choices[$definition['id']] = $label;
        }

        return $this->io()->select('Field type', $choices, scroll: 25);
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

        $fieldName = $this->input->getOption('field-name');
        $default = $this->getExistingEntityDisplayForDefaults('form')?->getComponent($fieldName)['type'] ?? null;

        return $this->io()->select('Field widget', $choices, $default);
    }

    protected function askRequired(): bool
    {
        $default = $this->getExistingFieldForDefaults()?->isRequired() ?? false;
        return $this->io()->confirm('Required', $default);
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
        $cardinality = $this->io()->select(
            'Allowed number of values',
            array_combine($choices, $choices),
            0
        );

        $limit = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
        while ($cardinality === 'Limited' && $limit < 1) {
            $limit = (int)$this->io()->ask('Allowed number of values', default: '1');
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

        // Event subscribers may customize $values as desired.
        $event = new FieldCreateFieldConfigValuesEvent($values, $this->input);
        $this->eventDispatcher->dispatch($event);
        $values = $event->getValues();

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

        // Event subscribers may customize $values as desired.
        $event = new FieldCreateFieldStorageConfigValuesEvent($values, $this->input);
        $this->eventDispatcher->dispatch($event);
        $values = $event->getValues();

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

        // Event subscribers may customize $values as desired.
        $event = new FieldCreateEntityDisplayValuesEvent($context, $values, $this->input);
        $this->eventDispatcher->dispatch($event);
        $values = $event->getValues();

        $storage = $this->getEntityDisplay($context);

        if (!$storage instanceof EntityDisplayInterface) {
            $this->logger->info(
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

        assert($storage instanceof EntityDisplayInterface);
        $storage->setComponent($fieldName, $values)->save();
    }

    protected function getEntityDisplay(string $context): ?EntityDisplayInterface
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        $return = $this->entityTypeManager
            ->getStorage(sprintf('entity_%s_display', $context))
            ->load(sprintf('%s.%s.default', $entityType, $bundle));
        assert($return instanceof EntityDisplayInterface || $return === null);
        return $return;
    }

    protected function logResult(FieldConfigInterface $field): void
    {
        $this->logger->success(
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
            $this->logger->success(
                dt('Further customisation can be done through the <href=%editForm>edit form</>.', [
                    '%editForm' => Url::fromRoute($routeName, $routeParams)->setAbsolute(true)->toString(),
                ]),
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

    protected function getExistingEntityDisplayForDefaults(string $context): ?EntityDisplayInterface
    {
        $existingBundle = $this->getExistingBundleForDefaults();
        if ($existingBundle === null) {
            return null;
        }

        $entityTypeId = $this->input->getArgument('entityType');
        $fieldName = $this->input->getOption('field-name');
        $storage = $this->entityTypeManager
            ->getStorage(sprintf('entity_%s_display', $context));
        $displays = $storage->loadByProperties([
            'targetEntityType' => $entityTypeId,
            'bundle' => $existingBundle,
        ]);

        foreach ($displays as $display) {
            assert($display instanceof EntityDisplayInterface);
            if ($display->getComponent($fieldName)) {
                return $display;
            }
        }

        return null;
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
