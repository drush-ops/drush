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
   * @param StorageInterface
   *   The storage object that the filtered data will be
   *   written to.  Provided in case the filter needs to
   *   read the existing configuration before writing it.
   *
   * @return array $data
   *   The filtered data.
   */
  public function filterWrite($name, array $data, StorageInterface $storage);

}
