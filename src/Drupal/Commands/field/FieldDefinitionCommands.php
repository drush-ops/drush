<?php

declare(strict_types=1);

namespace Drush\Drupal\Commands\field;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Field\WidgetPluginManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Defines field definition Drush commands.
 */
final class FieldDefinitionCommands extends DrushCommands {

    public function __construct(
        private readonly FieldTypePluginManagerInterface $typePluginManager,
        private readonly WidgetPluginManager $widgetPluginManager,
        private readonly FormatterPluginManager $formatterPluginManager,
    ) {
        parent::__construct();
    }

    #[CLI\Command(name: 'field:types')]
    #[CLI\FieldLabels(
        labels: [
            'id' => 'ID',
            'label' => 'Label',
            'default_widget' => 'Default Widget',
            'default_formatter' => 'Default Formatter',
            'settings' => 'Settings',
        ],
    )]
    #[CLI\Help(
        description: 'Lists field types.',
        synopsis: 'drush field:types [options]',
    )]
    #[CLI\FilterDefaultField(field: 'id')]
    public function types(array $options = ['format' => 'yaml']): RowsOfFields {
        $processor = static fn(array $definition): array => [
            'id' => $definition['id'],
            'label' => (string) $definition['label'],
            'default_widget' => $definition['default_widget'] ?? NULL,
            'default_formatter' => $definition['default_formatter'] ?? NULL,
            'settings' => BaseFieldDefinition::create($definition['id'])->getSettings(),
        ];
        $definitions = \array_map($processor, $this->typePluginManager->getDefinitions());
        return new RowsOfFields(
            self::encodeDefinitions($definitions, $options['format']),
        );
    }

    #[CLI\Command(name: 'field:widgets')]
    #[CLI\Option(name: 'field-type', description: 'Applicable field type')]
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
    #[CLI\Help(
        description: 'Lists field widgets.',
        synopsis: 'drush field:widgets [options]',
    )]
    #[CLI\FilterDefaultField(field: 'id')]
    public function widgets(array $options = ['format' => 'yaml', 'field-type' => NULL]): RowsOfFields {
        $processor = static fn(array $definition): array => [
            'id' => $definition['id'],
            'label' => (string) $definition['label'],
            'default_settings' => $definition['class']::defaultSettings(),
            'field_types' => $definition['field_types'],
        ];
        $definitions = \array_map($processor, $this->widgetPluginManager->getDefinitions());
        if ($options['field-type']) {
            $definitions = self::filterByFieldType($definitions, $options['field-type']);
        }
        return new RowsOfFields(
            self::encodeDefinitions($definitions, $options['format']),
        );
    }

    #[CLI\Command(name: 'field:formatters')]
    #[CLI\Option(name: 'field-type', description: 'Applicable field type')]
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
    #[CLI\Help(
        description: 'Lists field formatters.',
        synopsis: 'drush field:formatters [options]',
    )]
    #[CLI\FilterDefaultField(field: 'id')]
    public function formatters(array $options = ['format' => 'yaml', 'field-type' => NULL]): RowsOfFields {
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
            $definitions = self::filterByFieldType($definitions, $options['field-type']);
        }
        return new RowsOfFields(
            self::encodeDefinitions($definitions, $options['format']),
        );
    }

    /**
     * Encodes rows.
     *
     * Some output formats i.e. 'table' expect that each row to be a scalar
     * value.
     */
    private static function encodeDefinitions(array $definitions, string $format): mixed {
        $scalar_formats = ['table', 'csv', 'tsv', 'string'];
        $encode_data = static fn(mixed $data): mixed =>
            \is_array($data) && \in_array($format, $scalar_formats) ? \json_encode($data) : $data;
        $encode_definition = static fn(array $definition): mixed => \array_map($encode_data, $definition);
        return \array_map($encode_definition, $definitions);
    }

    /**
     * Filters definitions by applicable field types.
     */
    private static function filterByFieldType(array $definitions, string $search): array {
        $match = static fn(string $field_type): bool => \str_contains($field_type, $search);
        $total_matches = static fn(array $field_types): int => \count(\array_filter($field_types, $match));
        $has_matches = static fn(array $definition): bool => $total_matches($definition['field_types']) > 0;
        return \array_filter($definitions, $has_matches);
    }

}

