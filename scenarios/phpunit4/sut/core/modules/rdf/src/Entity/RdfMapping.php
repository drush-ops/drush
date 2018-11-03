<?php

namespace Drupal\rdf\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\rdf\RdfMappingInterface;

/**
 * Config entity for working with RDF mappings.
 *
 * @ConfigEntityType(
 *   id = "rdf_mapping",
 *   label = @Translation("RDF mapping"),
 *   label_singular = @Translation("RDF mapping item"),
 *   label_plural = @Translation("RDF mappings items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count RDF mapping item",
 *     plural = "@count RDF mapping items",
 *   ),
 *   config_prefix = "mapping",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   admin_permission = "administer site configuration",
 *   config_export = {
 *     "id",
 *     "targetEntityType",
 *     "bundle",
 *     "types",
 *     "fieldMappings",
 *   }
 * )
 */
class RdfMapping extends ConfigEntityBase implements RdfMappingInterface {

  /**
   * Unique ID for the config entity.
   *
   * @var string
   */
  protected $id;

  /**
   * Entity type to be mapped.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * Bundle to be mapped.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The RDF type mapping for this bundle.
   *
   * @var array
   */
  protected $types = [];

  /**
   * The mappings for fields on this bundle.
   *
   * @var array
   */
  protected $fieldMappings = [];

  /**
   * {@inheritdoc}
   */
  public function getPreparedBundleMapping() {
    return ['types' => $this->types];
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleMapping() {
    if (!empty($this->types)) {
      return ['types' => $this->types];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setBundleMapping(array $mapping) {
    if (isset($mapping['types'])) {
      $this->types = $mapping['types'];
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreparedFieldMapping($field_name) {
    $field_mapping = [
      'properties' => NULL,
      'datatype' => NULL,
      'datatype_callback' => NULL,
      'mapping_type' => NULL,
    ];
    if (isset($this->fieldMappings[$field_name])) {
      $field_mapping = array_merge($field_mapping, $this->fieldMappings[$field_name]);
    }
    return empty($field_mapping['properties']) ? [] : $field_mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapping($field_name) {
    if (isset($this->fieldMappings[$field_name])) {
      return $this->fieldMappings[$field_name];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldMapping($field_name, array $mapping = []) {
    $this->fieldMappings[$field_name] = $mapping;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->targetEntityType . '.' . $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Create dependency on the bundle.
    $entity_type = \Drupal::entityManager()->getDefinition($this->targetEntityType);
    $this->addDependency('module', $entity_type->getProvider());
    $bundle_config_dependency = $entity_type->getBundleConfigDependency($this->bundle);
    $this->addDependency($bundle_config_dependency['type'], $bundle_config_dependency['name']);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (\Drupal::entityManager()->hasHandler($this->targetEntityType, 'view_builder')) {
      \Drupal::entityManager()->getViewBuilder($this->targetEntityType)->resetCache();
    }
  }

}
