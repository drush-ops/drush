<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

class BaseFieldInfoCommands extends DrushCommands
{
    use AskBundleTrait;
    use FieldDefinitionRowsOfFieldsTrait;
    use ValidateEntityTypeTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManagerInterface */
    protected $entityFieldManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        EntityFieldManagerInterface $entityFieldManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * List all base fields of an entity type
     *
     * @command base-field:info
     * @aliases base-field-info,bfi
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
     * @usage drush base-field-info taxonomy_term
     *      List all base fields.
     * @usage drush base-field:info
     *      List all base fields and fill in the remaining information through prompts.
     */
    public function info(string $entityType, array $options = [
        'format' => 'table',
    ]): RowsOfFields
    {
        $fieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);

        return $this->getRowsOfFieldsByFieldDefinitions($fieldDefinitions);
    }
}
