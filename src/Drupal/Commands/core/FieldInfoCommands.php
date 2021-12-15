<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

class FieldInfoCommands extends DrushCommands
{
    use AskBundleTrait;
    use FieldDefinitionRowsOfFieldsTrait;
    use ValidateEntityTypeTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    }

    /**
     * List all configurable fields of an entity bundle
     *
     * @command field:info
     * @aliases field-info,fi
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @default-fields field_name,required,field_type,cardinality
     * @field-labels
     *      label: Label
     *      description: Description
     *      field_name: Field name
     *      field_type: Field type
     *      required: Required
     *      translatable: Translatable
     *      cardinality: Cardinality
     *      default_value: Default value
     *      default_value_callback: Default value callback
     *      allowed_values: Allowed values
     *      allowed_values_function: Allowed values function
     *      handler: Selection handler
     *      target_bundles: Target bundles
     * @filter-default-field field_name
     * @table-style default
     *
     * @usage drush field-info taxonomy_term tag
     *      List all fields.
     * @usage drush field:info
     *      List all fields and fill in the remaining information through prompts.
     *
     * @version 11.0
     */
    public function info(string $entityType, ?string $bundle = null, array $options = [
        'format' => 'table',
    ]): RowsOfFields
    {
        $this->validateEntityType($entityType);

        $this->input->setArgument('bundle', $bundle = $bundle ?? $this->askBundle());
        $this->validateBundle($entityType, $bundle);

        $fieldDefinitions = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => $entityType,
                'bundle' => $bundle,
            ]);

        return $this->getRowsOfFieldsByFieldDefinitions($fieldDefinitions);
    }
}
