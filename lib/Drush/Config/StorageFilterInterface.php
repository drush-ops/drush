<?php

/**
 * @file
 * Definition of Drush\Config\StorageFilter.
 */

namespace Drush\Config;

use Drupal\Core\Config\StorageInterface;

interface StorageFilterInterface {

  /**
   * Filters configuration data after it is read from storage.
   *
   * @param string $name
   *   The name of a configuration object to load.
   * @param array $data
   *   The configuration data to filter.
   *
   * @return array $data
   *   The filtered data.
   */
  public function filterRead($name, $data);

  /**
   * Filter configuration data before it is written to storage.
   *
   * @param string $name
   *   The name of a configuration object to save.
   * @param array $data
   *   The configuration data to filter.
   * @param StorageInterface $storage
   *   (optional) The storage object that the filtered data will be
   *   written to. Provided in case the filter needs to
   *   read the existing configuration before writing it.
   *
   * @return array $data
   *   The filtered data.
   */
  public function filterWrite($name, array $data, StorageInterface $storage = NULL);

  /**
   * Filters whether a configuration object exists.
   *
   * @param string $name
   *   The name of a configuration object to test.
   * @param bool $exists
   *   The previous result to alter.
   *
   * @return bool
   *   TRUE if the configuration object exists, FALSE otherwise.
   */
  public function filterExists($name, $exists);

  /**
   * Deletes a configuration object from the storage.
   *
   * @param string $name
   *   The name of a configuration object to delete.
   * @param bool $delete
   *   Whether the previous filter allows to delete.
   *
   * @return bool
   *   TRUE to allow deletion, FALSE otherwise.
   */
  public function filterDelete($name, $delete);

  /**
   * Filters read configuration data from the storage.
   *
   * @param array $names
   *   List of names of the configuration objects to load.
   * @param array $data
   *   A list of the configuration data stored for the configuration object name
   *   that could be loaded for the passed list of names.
   *
   * @return array
   *   A list of the configuration data stored for the configuration object name
   *   that could be loaded for the passed list of names.
   */
  public function filterReadMultiple(array $names, array $data);

  /**
   * Filters renaming a configuration object in the storage.
   *
   * @param string $name
   *   The name of a configuration object to rename.
   * @param string $new_name
   *   The new name of a configuration object.
   * @param bool $rename
   *   Allowing renaming by previous filters.
   *
   * @return bool
   *   TRUE to allow renaming, FALSE otherwise.
   */
  public function filterRename($name, $new_name, $rename);

  /**
   * Filters what listAll should return.
   *
   * @param string $prefix
   *   The prefix to search for. If omitted, all configuration object
   *   names that exist are returned.
   * @param array $data
   *   The data returned by the storage.
   *
   * @return array
   *   The filtered configuration set.
   */
  public function filterListAll($prefix, array $data);

  /**
   * Deletes configuration objects whose names start with a given prefix.
   *
   * Given the following configuration object names:
   * - node.type.article
   * - node.type.page
   *
   * Passing the prefix 'node.type.' will delete the above configuration
   * objects.
   *
   * @param string $prefix
   *   The prefix to search for. If omitted, all configuration
   *   objects that exist will be deleted.
   * @param bool $delete
   *   Whether to delete all or not.
   *
   * @return bool
   *   TRUE to allow deleting all, FALSE otherwise.
   */
  public function filterDeleteAll($prefix, $delete);

  /**
   * Allows the filter to react on creating a collection on the storage.
   *
   * A configuration storage can contain multiple sets of configuration objects
   * in partitioned collections. The collection name identifies the current
   * collection used.
   *
   * @param string $collection
   *   The collection name. Valid collection names conform to the following
   *   regex [a-zA-Z_.]. A storage does not need to have a collection set.
   *   However, if a collection is set, then storage should use it to store
   *   configuration in a way that allows retrieval of configuration for a
   *   particular collection.
   *
   * @return \Drupal\config_split\Config\StorageFilterInterface|NULL
   *   Return a filter that should participate in the collection. This is
   *   allows filters to act on different collections.
   */
  public function filterCreateCollection($collection);

  /**
   * Filter getting the existing collections.
   *
   * A configuration storage can contain multiple sets of configuration objects
   * in partitioned collections. The collection key name identifies the current
   * collection used.
   *
   * @param string[] $collections
   *   The array of existing collection names.
   *
   * @return array
   *   An array of existing collection names.
   */
  public function filterGetAllCollectionNames($collections);

  /**
   * Filter the name of the current collection the storage is using.
   *
   * @param string $collection
   *   The collection found by the storage.
   *
   * @return string
   *   The current collection name.
   */
  public function filterGetCollectionName($collection);

}
