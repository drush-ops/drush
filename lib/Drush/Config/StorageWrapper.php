<?php

/**
 * @file
 * Definition of Drush\Config\StorageWrapper.
 */

namespace Drush\Config;

use Drupal\Core\Config\StorageInterface;

class StorageWrapper implements StorageInterface {

  /**
   * The storage container that we are wrapping.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;
  protected $filters;

  /**
   * Create a StorageWrapper with some storage and a filter.
   */
  function __construct($storage, $filterOrFilters) {
    $this->storage = $storage;
    $this->filters = is_array($filterOrFilters) ? $filterOrFilters : array($filterOrFilters);
  }

  /**
   * Returns whether a configuration object exists.
   *
   * @param string $name
   *   The name of a configuration object to test.
   *
   * @return bool
   *   TRUE if the configuration object exists, FALSE otherwise.
   */
  public function exists($name) {
    return $this->storage->exists($name);
  }

  /**
   * Reads configuration data from the storage.
   *
   * @param string $name
   *   The name of a configuration object to load.
   *
   * @return array|bool
   *   The configuration data stored for the configuration object name. If no
   *   configuration data exists for the given name, FALSE is returned.
   */
  public function read($name) {
    $data = $this->storage->read($name);

    foreach ($this->filters as $filter) {
      $data = $filter->filterRead($name, $data);
    }

    return $data;
  }

  /**
   * Reads configuration data from the storage.
   *
   * @param array $names
   *   List of names of the configuration objects to load.
   *
   * @return array
   *   A list of the configuration data stored for the configuration object name
   *   that could be loaded for the passed list of names.
   */
  public function readMultiple(array $names) {
    $dataList = $this->storage->readMultiple($names);
    $result = array();

    foreach ($dataList as $name => $data) {
      foreach ($this->filters as $filter) {
        $data = $filter->filterRead($name, $data);
      }
      $result[$name] = $data;
    }

    return $result;
  }

  /**
   * Writes configuration data to the storage.
   *
   * @param string $name
   *   The name of a configuration object to save.
   * @param array $data
   *   The configuration data to write.
   *
   * @return bool
   *   TRUE on success, FALSE in case of an error.
   *
   * @throws \Drupal\Core\Config\StorageException
   *   If the back-end storage does not exist and cannot be created.
   */
  public function write($name, array $data) {
    foreach ($this->filters as $filter) {
      $data = $filter->filterWrite($name, $data, $this->storage);
    }

    return $this->storage->write($name, $data);
  }

  /**
   * Deletes a configuration object from the storage.
   *
   * @param string $name
   *   The name of a configuration object to delete.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise.
   */
  public function delete($name) {
    return $this->storage->delete($name);
  }

  /**
   * Renames a configuration object in the storage.
   *
   * @param string $name
   *   The name of a configuration object to rename.
   * @param string $new_name
   *   The new name of a configuration object.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise.
   */
  public function rename($name, $new_name) {
    return $this->storage->rename($name, $new_name);
  }

  /**
   * Encodes configuration data into the storage-specific format.
   *
   * @param array $data
   *   The configuration data to encode.
   *
   * @return string
   *   The encoded configuration data.
   *
   * This is a publicly accessible static method to allow for alternative
   * usages in data conversion scripts and also tests.
   */
  public function encode($data) {
    return $this->storage->encode($data);
  }

  /**
   * Decodes configuration data from the storage-specific format.
   *
   * @param string $raw
   *   The raw configuration data string to decode.
   *
   * @return array
   *   The decoded configuration data as an associative array.
   *
   * This is a publicly accessible static method to allow for alternative
   * usages in data conversion scripts and also tests.
   */
  public function decode($raw) {
    return $this->storage->decode($raw);
  }

  /**
   * Gets configuration object names starting with a given prefix.
   *
   * Given the following configuration objects:
   * - node.type.article
   * - node.type.page
   *
   * Passing the prefix 'node.type.' will return an array containing the above
   * names.
   *
   * @param string $prefix
   *   (optional) The prefix to search for. If omitted, all configuration object
   *   names that exist are returned.
   *
   * @return array
   *   An array containing matching configuration object names.
   */
  public function listAll($prefix = '') {
    return $this->storage->listAll($prefix);
  }

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
   *   (optional) The prefix to search for. If omitted, all configuration
   *   objects that exist will be deleted.
   *
   * @return boolean
   *   TRUE on success, FALSE otherwise.
   */
  public function deleteAll($prefix = '') {
    return $this->storage->deleteAll($prefix);
  }

  /**
   * Creates a collection on the storage.
   *
   * A configuration storage can contain multiple sets of configuration objects
   * in partitioned collections. The collection name identifies the current
   * collection used.
   *
   * Implementations of this method must provide a new instance to avoid side
   * effects caused by the fact that Config objects have their storage injected.
   *
   * @param string $collection
   *   The collection name. Valid collection names conform to the following
   *   regex [a-zA-Z_.]. A storage does not need to have a collection set.
   *   However, if a collection is set, then storage should use it to store
   *   configuration in a way that allows retrieval of configuration for a
   *   particular collection.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   A new instance of the storage backend with the collection set.
   */
  public function createCollection($collection) {
    return $this->storage->createCollection($collection);
  }

  /**
   * Gets the existing collections.
   *
   * A configuration storage can contain multiple sets of configuration objects
   * in partitioned collections. The collection key name identifies the current
   * collection used.
   *
   * @return array
   *   An array of existing collection names.
   */
  public function getAllCollectionNames() {
    return $this->storage->getAllCollectionNames();
  }

  /**
   * Gets the name of the current collection the storage is using.
   *
   * @return string
   *   The current collection name.
   */
  public function getCollectionName() {
    return $this->storage->getCollectionName();
  }

}
