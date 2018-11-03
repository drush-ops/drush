<?php

namespace Drupal\Core\KeyValueStore;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Database\Query\Merge;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Defines a default key/value store implementation.
 *
 * This is Drupal's default key/value store implementation. It uses the database
 * to store key/value data.
 */
class DatabaseStorage extends StorageBase {

  use DependencySerializationTrait;

  /**
   * The serialization class to use.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The name of the SQL table to use.
   *
   * @var string
   */
  protected $table;

  /**
   * Overrides Drupal\Core\KeyValueStore\StorageBase::__construct().
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   The serialization class to use.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param string $table
   *   The name of the SQL table to use, defaults to key_value.
   */
  public function __construct($collection, SerializationInterface $serializer, Connection $connection, $table = 'key_value') {
    parent::__construct($collection);
    $this->serializer = $serializer;
    $this->connection = $connection;
    $this->table = $table;
  }

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    return (bool) $this->connection->query('SELECT 1 FROM {' . $this->connection->escapeTable($this->table) . '} WHERE collection = :collection AND name = :key', [
      ':collection' => $this->collection,
      ':key' => $key,
    ])->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys) {
    $values = [];
    try {
      $result = $this->connection->query('SELECT name, value FROM {' . $this->connection->escapeTable($this->table) . '} WHERE name IN ( :keys[] ) AND collection = :collection', [':keys[]' => $keys, ':collection' => $this->collection])->fetchAllAssoc('name');
      foreach ($keys as $key) {
        if (isset($result[$key])) {
          $values[$key] = $this->serializer->decode($result[$key]->value);
        }
      }
    }
    catch (\Exception $e) {
      // @todo: Perhaps if the database is never going to be available,
      // key/value requests should return FALSE in order to allow exception
      // handling to occur but for now, keep it an array, always.
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    $result = $this->connection->query('SELECT name, value FROM {' . $this->connection->escapeTable($this->table) . '} WHERE collection = :collection', [':collection' => $this->collection]);
    $values = [];

    foreach ($result as $item) {
      if ($item) {
        $values[$item->name] = $this->serializer->decode($item->value);
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    $this->connection->merge($this->table)
      ->keys([
        'name' => $key,
        'collection' => $this->collection,
      ])
      ->fields(['value' => $this->serializer->encode($value)])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($key, $value) {
    $result = $this->connection->merge($this->table)
      ->insertFields([
        'collection' => $this->collection,
        'name' => $key,
        'value' => $this->serializer->encode($value),
      ])
      ->condition('collection', $this->collection)
      ->condition('name', $key)
      ->execute();
    return $result == Merge::STATUS_INSERT;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($key, $new_key) {
    $this->connection->update($this->table)
      ->fields(['name' => $new_key])
      ->condition('collection', $this->collection)
      ->condition('name', $key)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys) {
    // Delete in chunks when a large array is passed.
    while ($keys) {
      $this->connection->delete($this->table)
        ->condition('name', array_splice($keys, 0, 1000), 'IN')
        ->condition('collection', $this->collection)
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->connection->delete($this->table)
      ->condition('collection', $this->collection)
      ->execute();
  }

}
