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
   * {@inheritdoc}
   */
  public function exists($name) {
    $exists = $this->storage->exists($name);
    foreach ($this->filters as $filter) {
      $exists = $filter->filterExists($name, $exists, $this->storage);
    }
    return $exists;
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $data = $this->storage->read($name);

    foreach ($this->filters as $filter) {
      $data = $filter->filterRead($name, $data, $this->storage);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $dataList = $this->storage->readMultiple($names);
    $result = array();
    // We also need to read the configs which are only on one of the two storages.
    foreach($names as $name) {
      if (!isset($dataList[$name])) {
        $dataList[$name] = NULL;
      }
    }
    foreach ($dataList as $name => $data) {
      foreach ($this->filters as $filter) {
        $data = $filter->filterRead($name, $data, $this->storage);
      }
      $result[$name] = $data;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    foreach ($this->filters as $filter) {
      $data = $filter->filterWrite($name, $data, $this->storage);
    }
    // filterWrite might return NULL, which means the config should be deleted.
    if (!isset($data)) {
      return $this->storage->delete($name);
    }
    return $this->storage->write($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    $doDelete = TRUE;
    foreach ($this->filters as $filter) {
      $doDelete = $filter->filterDelete($doDelete, $name, $this->storage);
    }
    if ($doDelete) {
      return $this->storage->delete($name);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    foreach ($this->filters as $filter) {
      $new_name = $filter->filterRename($new_name, $name, $this->storage);
    }
    return $this->storage->rename($name, $new_name);
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $this->storage->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $this->storage->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    $list = $this->storage->listAll($prefix);
    // Allow filters to add configs to the list.
    foreach ($this->filters as $filter) {
      $list = $filter->filterListAll($list, $this->storage, $prefix);
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    $list = $this->storage->listAll();
    $result = TRUE;
    foreach($list as $name) {
      $result = $this->delete($name) ? $result : FALSE;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return $this->storage->createCollection($collection);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    return $this->storage->getAllCollectionNames();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->storage->getCollectionName();
  }

}
