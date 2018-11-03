<?php

namespace Drupal\migrate\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Migration plugin manager interface.
 */
interface MigrationPluginManagerInterface extends PluginManagerInterface {

  /**
   * Create pre-configured instance of plugin derivatives.
   *
   * @param array $id
   *   Either the plugin ID or the base plugin ID of the plugins being
   *   instantiated. Also accepts an array of plugin IDs and an empty array to
   *   load all plugins.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instances. Keyed by the
   *   plugin ID.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface[]
   *   Fully configured plugin instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If an instance cannot be created, such as if the ID is invalid.
   */
  public function createInstances($id, array $configuration = []);

  /**
   * Creates a stub migration plugin from a definition array.
   *
   * @param array $definition
   *   The migration definition. If an 'id' key is set then this will be used as
   *   the migration ID, if not a random ID will be assigned.
   *
   * @return \Drupal\migrate\Plugin\Migration
   *   The stub migration.
   */
  public function createStubMigration(array $definition);

  /**
   * Create migrations given a tag.
   *
   * @param string $tag
   *   A migration tag we want to filter by.
   *
   * @return array|\Drupal\migrate\Plugin\MigrationInterface[]
   *   An array of migration objects with the given tag.
   */
  public function createInstancesByTag($tag);

}
