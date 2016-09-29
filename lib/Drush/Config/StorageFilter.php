<?php

/**
 * @file
 * Definition of Drush\Config\StorageFilter.
 */

namespace Drush\Config;

use Drupal\Core\Config\StorageInterface;

interface StorageFilter {

  /**
   * Filters configuration data after it is read from storage.
   *
   * @param string $name
   *   The name of a configuration object to load.
   * @param array $data
   *   The configuration data to filter.
   * @param StorageInterface $storage
   *   The storage object that the filtered data will be
   *   read from.  Provided in case the filter needs to
   *   read the existing configuration before writing it.
   *
   * @return array $data
   *   The filtered data.
   */
  public function filterRead($name, $data, StorageInterface $storage);

  /**
   * Filter configuration data before it is written to storage.
   *
   * @param string $name
   *   The name of a configuration object to save.
   * @param array $data
   *   The configuration data to filter.
   * @param StorageInterface $storage
   *   The storage object that the filtered data will be
   *   written to.  Provided in case the filter needs to
   *   read the existing configuration before writing it.
   *
   * @return array $data
   *   The filtered data.
   */
  public function filterWrite($name, array $data, StorageInterface $storage);

  /**
   * Alter the result of the exists() function.
   * @param bool $exists
   * The value before the filter.
   * @param string $name
   * The name of a configuration object to check.
   * @param \Drupal\Core\Config\StorageInterface $storage
   * The storage object containing the data we want to filter.
   * @return bool
   * Whether or not the config exists after the filter is applied.
   */
  public function filterExists($exists, $name, StorageInterface $storage);

  /**
   * Can avoid the deletion of a config.
   * @param bool $doDelete
   * Whether to delete or not before the filter is applied.
   * @param string $name
   * The name of a configuration object to delete.
   * @param \Drupal\Core\Config\StorageInterface $storage
   * The storage object containing the data we want to filter.
   * @return bool
   * Whether or not the config should be deleted.
   */
  public function filterDelete($doDelete, $name, StorageInterface $storage);

  /**
   * Alter the result of the rename() function.
   * @param bool $new_name
   * The new name before the filter.
   * @param string $name
   * The name of a configuration object to check, before the rename.
   * @param \Drupal\Core\Config\StorageInterface $storage
   * The storage object containing the data we want to filter.
   * @return string
   * The new name that should actually be used.
   */
  public function rename($new_name, $name, StorageInterface $storage);

  /**
   * Alter the result of the listAll() function.
   * @param array $list
   * The list of config names before the filter.
   * @param \Drupal\Core\Config\StorageInterface $storage
   * The storage object containing the data we want to filter.
   * @param string $prefix
   * (optional) The prefix to search for.
   * @return array
   * The filtered list.
   */
  public function filterListAll($list, StorageInterface $storage, $prefix = '');

}
