<?php

namespace Drupal\file\FileUsage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\file\FileInterface;

/**
 * Defines the database file usage backend. This is the default Drupal backend.
 */
class DatabaseFileUsageBackend extends FileUsageBase {

  /**
   * The database connection used to store file usage information.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The name of the SQL table used to store file usage information.
   *
   * @var string
   */
  protected $tableName;

  /**
   * Construct the DatabaseFileUsageBackend.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection which will be used to store the file usage
   *   information.
   * @param string $table
   *   (optional) The table to store file usage info. Defaults to 'file_usage'.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   (optional) The config factory.
   */
  public function __construct(Connection $connection, $table = 'file_usage', ConfigFactoryInterface $config_factory = NULL) {
    parent::__construct($config_factory);
    $this->connection = $connection;

    $this->tableName = $table;
  }

  /**
   * {@inheritdoc}
   */
  public function add(FileInterface $file, $module, $type, $id, $count = 1) {
    $this->connection->merge($this->tableName)
      ->keys([
        'fid' => $file->id(),
        'module' => $module,
        'type' => $type,
        'id' => $id,
      ])
      ->fields(['count' => $count])
      ->expression('count', 'count + :count', [':count' => $count])
      ->execute();

    parent::add($file, $module, $type, $id, $count);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(FileInterface $file, $module, $type = NULL, $id = NULL, $count = 1) {
    // Delete rows that have a exact or less value to prevent empty rows.
    $query = $this->connection->delete($this->tableName)
      ->condition('module', $module)
      ->condition('fid', $file->id());
    if ($type && $id) {
      $query
        ->condition('type', $type)
        ->condition('id', $id);
    }
    if ($count) {
      $query->condition('count', $count, '<=');
    }
    $result = $query->execute();

    // If the row has more than the specified count decrement it by that number.
    if (!$result && $count > 0) {
      $query = $this->connection->update($this->tableName)
        ->condition('module', $module)
        ->condition('fid', $file->id());
      if ($type && $id) {
        $query
          ->condition('type', $type)
          ->condition('id', $id);
      }
      $query->expression('count', 'count - :count', [':count' => $count]);
      $query->execute();
    }

    parent::delete($file, $module, $type, $id, $count);
  }

  /**
   * {@inheritdoc}
   */
  public function listUsage(FileInterface $file) {
    $result = $this->connection->select($this->tableName, 'f')
      ->fields('f', ['module', 'type', 'id', 'count'])
      ->condition('fid', $file->id())
      ->condition('count', 0, '>')
      ->execute();
    $references = [];
    foreach ($result as $usage) {
      $references[$usage->module][$usage->type][$usage->id] = $usage->count;
    }
    return $references;
  }

}
