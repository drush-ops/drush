<?php

/**
 * @file
 * Definition of Drush\Config\StorageFilter.
 */

namespace Drush\Config;

use Drupal\Core\Config\StorageInterface;

/**
 * This filter adjusts the data going to and coming from
 * the core.extension configuration object.
 *
 * Modules named in this list are ignored during config-import
 * and config-export operations.  What this means in practical terms is:
 *
 *   * During a 'read' operation, if a named module is enabled in the
 *     active configuration, then it will remain enabled after the
 *     import.  If it is disabled in the active configuration, then it
 *     will remain disabled.  The value from the data being imported
 *     is ignored.
 *
 *   * During a 'write' operation, if a named module is enabled in
 *     the configuration already written out on the target storage
 *     object, then it will remain enabled.  If it is disabled in
 *     the previously-exported data, then it will remain disabled.  If
 *     there is no existing export (first-time export), then all of
 *     the named modules will be excluded (disabled) from the export.
 *     The current enabled / disabled state of the module in the
 *     active configuration is ignored.
 *
 * The data from core.extension looks like this:
 *
 * module:
 *   modulename: weight
 * theme:
 *   themename: weight
 *
 * The "adjustments" lists is just an array where the values
 * are the module names to exclude from import / export, as
 * described above.
 */
class CoreExtensionFilter implements StorageFilter {

  protected $adjustments;

  function __construct($adjustments = array()) {
    $this->adjustments = $adjustments;
  }

  public function filterRead($name, $data) {
    if ($name != 'core.extension') {
      return $data;
    }
    $active_storage = \Drupal::service('config.storage');
    return $this->filterOutIgnored($data, $active_storage->read($name));
  }

  public function filterWrite($name, array $data, StorageInterface $storage) {
    if ($name != 'core.extension') {
      return $data;
    }
    $originalData = $storage->read($name);
    return $this->filterOutIgnored($data, $storage->read($name));
  }

  protected function filterOutIgnored($data, $originalData) {
    foreach($this->adjustments as $module) {
      if (is_array($originalData) && array_key_exists($module, $originalData['module'])) {
        $data['module'][$module] = $originalData['module'][$module];
      }
      else {
        unset($data['module'][$module]);
      }
    }
    return $data;

  }
}
