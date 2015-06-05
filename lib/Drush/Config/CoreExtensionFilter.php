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
 * Modules named in this list cause the following actions:
 *
 *   * On read, items from the "adjustments" list are examined
 *     one at a time.  If their value is numeric, it is used as
 *     the module weight, and merged in with the data being read
 *     from the existing core.extension configuration item, forcing
 *     the extension to be enabled.  If the value is non-numeric,
 *     such as FALSE or NULL or "disable", then the item will be
 *     removed from the list, forcing the extension to be disabled.
 *
 *   * On write, the existing storage configuration object
 *     is read.  Items in the data being written are replaced
 *     with their value from the existing configuration data
 *     iff the item exists in the "adjustments" list.
 *
 * The data from core.extension looks like this:
 *
 * module:
 *   modulename: weight
 * theme:
 *   themename: weight
 *
 * The "adjustments" lists should contain a list of "modulename: weight"
 * pairs.  Use non-numeric weights to indicate "disabled".  Only
 * the module list is adjusted; the theme list is not altered.
 */
class CoreExtensionFilter implements StorageFilter {

  protected $adjustments;
  protected $alwaysDisabled;

  function __construct($adjustments = array()) {
    $this->adjustments = $adjustments;
  }

  public function filterRead($name, $data) {
    if ($name != 'core.extension') {
      return $data;
    }
    foreach($this->adjustments as $module => $weight) {
      if (is_numeric($weight)) {
        $data['module'][$module] = $weight;
      }
      else {
        unset($data['module'][$module]);
      }
    }
    return $data;
  }

  public function filterWrite($name, array $data, StorageInterface $storage) {
    if ($name != 'core.extension') {
      return $data;
    }
    $originalData = $storage->read($name);
    foreach($this->adjustments as $module => $weight) {
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
