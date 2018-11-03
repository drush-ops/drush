<?php

namespace Drupal\field\Plugin\migrate\process;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\process\StaticMap;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\MigrateCckFieldPluginManagerInterface;
use Drupal\migrate_drupal\Plugin\MigrateFieldPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateProcessPlugin(
 *   id = "field_type"
 * )
 */
class FieldType extends StaticMap implements ContainerFactoryPluginInterface {

  /**
   * The cckfield plugin manager.
   *
   * @var \Drupal\migrate_drupal\Plugin\MigrateCckFieldPluginManagerInterface
   */
  protected $cckPluginManager;

  /**
   * The field plugin manager.
   *
   * @var \Drupal\migrate_drupal\Plugin\MigrateFieldPluginManagerInterface
   */
  protected $fieldPluginManager;

  /**
   * The migration object.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * Constructs a FieldType plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate_drupal\Plugin\MigrateCckFieldPluginManagerInterface $cck_plugin_manager
   *   The cckfield plugin manager.
   * @param \Drupal\migrate_drupal\Plugin\MigrateFieldPluginManagerInterface $field_plugin_manager
   *   The field plugin manager.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration being run.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrateCckFieldPluginManagerInterface $cck_plugin_manager, MigrateFieldPluginManagerInterface $field_plugin_manager, MigrationInterface $migration = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cckPluginManager = $cck_plugin_manager;
    $this->fieldPluginManager = $field_plugin_manager;
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migrate.cckfield'),
      $container->get('plugin.manager.migrate.field'),
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $field_type = is_array($value) ? $value[0] : $value;
    try {
      $plugin_id = $this->fieldPluginManager->getPluginIdFromFieldType($field_type, [], $this->migration);
      return $this->fieldPluginManager->createInstance($plugin_id, [], $this->migration)->getFieldType($row);
    }
    catch (PluginNotFoundException $e) {
      try {
        $plugin_id = $this->cckPluginManager->getPluginIdFromFieldType($field_type, [], $this->migration);
        return $this->cckPluginManager->createInstance($plugin_id, [], $this->migration)->getFieldType($row);
      }
      catch (PluginNotFoundException $e) {
        return parent::transform($value, $migrate_executable, $row, $destination_property);
      }
    }
  }

}
