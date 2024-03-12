<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

final class FieldBaseInfoCommands extends DrushCommands
{
    use AutowireTrait;

    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;
    use FieldDefinitionRowsOfFieldsTrait;

    const BASE_INFO = 'field:base-info';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        protected EntityFieldManagerInterface $entityFieldManager
    ) {
    }

    /**
     * List all base fields of an entity type
     */
    #[CLI\Command(name: self::BASE_INFO, aliases: ['field-base-info', 'fbi'])]
    #[CLI\Argument(name: 'entityType', description: 'The machine name of the entity type.')]
    #[CLI\Option(name: 'show-machine-names', description: 'Show machine names instead of labels in option lists.')]
    #[CLI\DefaultTableFields(fields: [
        'field_name',
        'required',
        'field_type',
        'cardinality',
    ])]
    #[CLI\FieldLabels(labels: [
        'label' => 'Label',
        'description' => 'Description',
        'field_name' => 'Field name',
        'field_type' => 'Field type',
        'required' => 'Required',
        'translatable' => 'Translatable',
        'cardinality' => 'Cardinality',
        'default_value' => 'Default value',
        'default_value_callback' => 'Default value callback',
        'allowed_values' => 'Allowed values',
        'allowed_values_function' => 'Allowed values function',
        'handler' => 'Selection handler',
        'target_bundles' => 'Target bundles',
    ])]
    #[CLI\FilterDefaultField(field: 'field_name')]
    #[CLI\Usage(name: 'field:base-info taxonomy_term', description: 'List all base fields.')]
    #[CLI\Usage(name: self::BASE_INFO, description: 'List all base fields and fill in the remaining information through prompts.')]
    #[CLI\Complete(method_name_or_callable: 'complete')]
    #[CLI\Version(version: '11.0')]
    public function info(?string $entityType = null, array $options = [
        'format' => 'table',
    ]): RowsOfFields
    {
        $this->input->setArgument('entityType', $entityType = $entityType ?? $this->askEntityType());
        $this->validateEntityType($entityType);

        $fieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);

        return $this->getRowsOfFieldsByFieldDefinitions($fieldDefinitions);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionName() === 'entityType') {
            $suggestions->suggestValues(array_keys($this->getFieldableEntityTypes()));
        }
    }
}
