<?php

namespace Drush\Drupal\Commands\field;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

class FieldBaseInfoCommands extends DrushCommands
{
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;
    use FieldDefinitionRowsOfFieldsTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfoInterface */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManagerInterface */
    protected $entityFieldManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        EntityFieldManagerInterface $entityFieldManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * List all base fields of an entity type
     *
     * @command field:base-info
     * @aliases field-base-info,fbi
     *
     * @param string $entityType
     *      The machine name of the entity type
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
     * @usage drush field:base-info taxonomy_term
     *      List all base fields.
     * @usage drush field:base-info
     *      List all base fields and fill in the remaining information through prompts.
     *
     * @version 11.0
     */
    public function info(?string $entityType = null, array $options = [
        'format' => 'table',
    ]): RowsOfFields
    {
        $this->input->setArgument('entityType', $entityType = $entityType ?? $this->askEntityType());
        $this->validateEntityType($entityType);

        $fieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);

        return $this->getRowsOfFieldsByFieldDefinitions($fieldDefinitions);
    }
}
