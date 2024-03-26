<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Field\WidgetPluginManager;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FieldDefinitionCommands extends DrushCommands
{
    use AutowireTrait;

    const TYPES = 'field:types';
    const WIDGETS = 'field:widgets';
    const FORMATTERS = 'field:formatters';

    public function __construct(
        private readonly FieldTypePluginManagerInterface $typePluginManager,
        // @todo These attributes should not be needed but services aren't found otherwise.
        #[Autowire(service: 'plugin.manager.field.widget')]
        private readonly WidgetPluginManager $widgetPluginManager,
        #[Autowire(service: 'plugin.manager.field.formatter')]
        private readonly FormatterPluginManager $formatterPluginManager,
    ) {
        parent::__construct();
    }

    #[CLI\Command(name: self::TYPES)]
    #[CLI\FieldLabels(
        labels: [
            'id' => 'ID',
            'label' => 'Label',
            'default_widget' => 'Default Widget',
            'default_formatter' => 'Default Formatter',
            'settings' => 'Settings',
        ],
    )]
    #[CLI\Help(description: 'Lists field types.')]
    #[CLI\Usage(
        name: 'drush field:types',
        description: 'List all registered field types.'
    ),
    ]
    #[CLI\FilterDefaultField(field: 'id')]
    public function types(array $options = ['format' => 'yaml']): UnstructuredListData
    {
        $processor = static fn(array $definition): array => [
            'id' => $definition['id'],
            'label' => (string) $definition['label'],
            'default_widget' => $definition['default_widget'] ?? null,
            'default_formatter' => $definition['default_formatter'] ?? null,
            'settings' => BaseFieldDefinition::create($definition['id'])->getSettings(),
        ];
        $definitions = \array_map($processor, $this->typePluginManager->getDefinitions());
        return new UnstructuredListData($definitions);
    }

    #[CLI\Command(name: self::WIDGETS)]
    #[CLI\Option(name: 'field-type', description: 'Applicable field type.')]
    #[CLI\FieldLabels(
        labels: [
            'id' => 'ID',
            'label' => 'Label',
            'default_settings' => 'Default Settings',
            'field_types' => 'Field types',
            'settings' => 'Settings',
            'class' => 'Class',
            'provider' => 'Provider',
        ],
    )]
    #[CLI\DefaultFields(fields: ['id', 'label', 'default_settings', 'field_types'])]
    #[CLI\Help(description: 'Lists field widgets.')]
    #[CLI\Usage(
        name: 'drush field:widgets --field-type=entity_reference',
        description: 'Lists field widgets applicable for entity reference fields.'
    ),
    ]
    #[CLI\FilterDefaultField(field: 'id')]
    #[CLI\Complete(method_name_or_callable: 'complete')]
    public function widgets(array $options = ['format' => 'yaml', 'field-type' => self::REQ]): UnstructuredListData
    {
        $processor = static fn(array $definition): array => [
            'id' => $definition['id'],
            'label' => (string) $definition['label'],
            'default_settings' => $definition['class']::defaultSettings(),
            'field_types' => $definition['field_types'],
        ];
        $definitions = \array_map($processor, $this->widgetPluginManager->getDefinitions());
        if ($options['field-type']) {
            $definitions = $this->filterByFieldType($definitions, $options['field-type']);
        }
        return new UnstructuredListData($definitions);
    }

    #[CLI\Command(name: self::FORMATTERS)]
    #[CLI\Option(name: 'field-type', description: 'Applicable field type.')]
    #[CLI\FieldLabels(
        labels: [
            'id' => 'ID',
            'label' => 'Label',
            'default_settings' => 'Default Settings',
            'field_types' => 'Field types',
            'settings' => 'Settings',
            'class' => 'Class',
            'provider' => 'Provider',
        ],
    )]
    #[CLI\DefaultFields(fields: ['id', 'label', 'default_settings', 'field_types'])]
    #[CLI\Help(description: 'Lists field formatters.')]
    #[CLI\Usage(
        name: 'drush field:formatters --field-type=entity_reference',
        description: 'Lists field formatters applicable for entity reference fields.'
    ),
    ]
    #[CLI\FilterDefaultField(field: 'id')]
    #[CLI\Complete(method_name_or_callable: 'complete')]
    public function formatters(array $options = ['format' => 'yaml', 'field-type' => self::REQ]): UnstructuredListData
    {
        $processor = static fn(array $definition): array => [
            'id' => $definition['id'],
            'label' => (string) $definition['label'],
            'default_settings' => $definition['class']::defaultSettings(),
            'field_types' => $definition['field_types'],
            'class' => $definition['class'],
            'provider' => $definition['provider'],
        ];
        $definitions = \array_map($processor, $this->formatterPluginManager->getDefinitions());
        if ($options['field-type']) {
            $definitions = $this->filterByFieldType($definitions, $options['field-type']);
        }
        return new UnstructuredListData($definitions);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionType() === CompletionInput::TYPE_OPTION_VALUE) {
            if ($input->getCompletionName() === 'field-type') {
                $fieldTypes = $this->typePluginManager->getDefinitions();
                $suggestions->suggestValues(array_keys($fieldTypes));
            }
        }
    }

    /**
     * Filters definitions by applicable field types.
     */
    private function filterByFieldType(array $definitions, string $search): array
    {
        $match = static fn(string $field_type): bool => \str_contains($field_type, $search);
        $total_matches = static fn(array $field_types): int => \count(\array_filter($field_types, $match));
        $has_matches = static fn(array $definition): bool => $total_matches($definition['field_types']) > 0;
        return \array_filter($definitions, $has_matches);
    }
}
