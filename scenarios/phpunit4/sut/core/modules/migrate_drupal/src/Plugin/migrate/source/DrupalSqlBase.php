<?php

namespace Drupal\migrate_drupal\Plugin\migrate\source;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for source plugins using a Drupal database as a source.
 *
 * Provides general purpose helper methods that are commonly needed
 * when writing source plugins that use a Drupal database as a source, for
 * example:
 * - Check if the given module exists in the source database.
 * - Read Drupal configuration variables from the source database.
 *
 * For a full list, refer to the methods of this class.
 *
 * For available configuration keys, refer to the parent classes:
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 */
abstract class DrupalSqlBase extends SqlBase implements ContainerFactoryPluginInterface, DependentPluginInterface {

  use DependencyTrait;

  /**
   * The contents of the system table.
   *
   * @var array
   */
  protected $systemData;

  /**
   * If the source provider is missing.
   *
   * @var bool
   */
  protected $requirements = TRUE;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityManagerInterface $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state);
    $this->entityManager = $entity_manager;
  }

  /**
   * Retrieves all system data information from the source Drupal database.
   *
   * @return array
   *   List of system table information keyed by type and name.
   */
  public function getSystemData() {
    if (!isset($this->systemData)) {
      $this->systemData = [];
      try {
        $results = $this->select('system', 's')
          ->fields('s')
          ->execute();
        foreach ($results as $result) {
          $this->systemData[$result['type']][$result['name']] = $result;
        }
      }
      catch (\Exception $e) {
        // The table might not exist for example in tests.
      }
    }
    return $this->systemData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements() {
    if ($this->pluginDefinition['requirements_met'] === TRUE) {
      if (isset($this->pluginDefinition['source_module'])) {
        if ($this->moduleExists($this->pluginDefinition['source_module'])) {
          if (isset($this->pluginDefinition['minimum_schema_version']) && !$this->getModuleSchemaVersion($this->pluginDefinition['source_module']) < $this->pluginDefinition['minimum_schema_version']) {
            throw new RequirementsException('Required minimum schema version ' . $this->pluginDefinition['minimum_schema_version'], ['minimum_schema_version' => $this->pluginDefinition['minimum_schema_version']]);
          }
        }
        else {
          throw new RequirementsException('The module ' . $this->pluginDefinition['source_module'] . ' is not enabled in the source site.', ['source_module' => $this->pluginDefinition['source_module']]);
        }
      }
    }
    parent::checkRequirements();
  }

  /**
   * Retrieves a module schema_version from the source Drupal database.
   *
   * @param string $module
   *   Name of module.
   *
   * @return mixed
   *   The current module schema version on the origin system table or FALSE if
   *   not found.
   */
  protected function getModuleSchemaVersion($module) {
    $system_data = $this->getSystemData();
    return isset($system_data['module'][$module]['schema_version']) ? $system_data['module'][$module]['schema_version'] : FALSE;
  }

  /**
   * Checks if a given module is enabled in the source Drupal database.
   *
   * @param string $module
   *   Name of module to check.
   *
   * @return bool
   *   TRUE if module is enabled on the origin system, FALSE if not.
   */
  protected function moduleExists($module) {
    $system_data = $this->getSystemData();
    return !empty($system_data['module'][$module]['status']);
  }

  /**
   * Reads a variable from a source Drupal database.
   *
   * @param $name
   *   Name of the variable.
   * @param $default
   *   The default value.
   * @return mixed
   */
  protected function variableGet($name, $default) {
    try {
      $result = $this->select('variable', 'v')
        ->fields('v', ['value'])
        ->condition('name', $name)
        ->execute()
        ->fetchField();
    }
    // The table might not exist.
    catch (\Exception $e) {
      $result = FALSE;
    }
    return $result !== FALSE ? unserialize($result) : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // Generic handling for Drupal source plugin constants.
    if (isset($this->configuration['constants']['entity_type'])) {
      $this->addDependency('module', $this->entityManager->getDefinition($this->configuration['constants']['entity_type'])->getProvider());
    }
    if (isset($this->configuration['constants']['module'])) {
      $this->addDependency('module', $this->configuration['constants']['module']);
    }
    return $this->dependencies;
  }

}
