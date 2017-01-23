<?php

namespace Drush\Config;

use Drupal\Core\Config\StorageInterface;

/**
 * Class StorageFilterBase.
 *
 * Pass everything along as it came in.
 */
class StorageFilterBase implements StorageFilterInterface{

  /**
   * The source storage on which the filter operations are performed.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $source;

  /**
   * The wrapped storage which calls the filter.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $wrapped;

  /**
   * {@inheritdoc}
   */
  public function setSourceStorage(StorageInterface $storage) {
    $this->source = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function setWrappedStorage(StorageInterface $storage) {
    $this->wrapped = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function filterRead($name, $data) {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterWrite($name, array $data) {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterWriteEmptyIsDelete($name) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function filterExists($name, $exists) {
    return $exists;
  }

  /**
   * {@inheritdoc}
   */
  public function filterDelete($name, $delete) {
    return $delete;
  }

  /**
   * {@inheritdoc}
   */
  public function filterReadMultiple(array $names, array $data) {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterRename($name, $new_name, $rename) {
    return $rename;
  }

  /**
   * {@inheritdoc}
   */
  public function filterListAll($prefix, array $data) {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function filterDeleteAll($prefix, $delete) {
    return $delete;
  }

  /**
   * {@inheritdoc}
   */
  public function filterCreateCollection($collection) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function filterGetAllCollectionNames($collections) {
    return $collections;
  }

  /**
   * {@inheritdoc}
   */
  public function filterGetCollectionName($collection) {
    return $collection;
  }

}
