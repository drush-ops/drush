<?php

/**
 * @file
 * Definition of Drush\Config\StorageFilter.
 */

namespace Drush\Config;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManager;

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

  public function filterRead($name, $data, StorageInterface $storage) {
    $active_storage = \Drupal::service('config.storage');
    if ($name == 'core.extension') {
      return $this->filterOutIgnored($data, $active_storage->read($name));
    }

    $dependent_configs = $this->getAllDependentConfigs($storage);
    if (in_array($name, $dependent_configs)) {
      if ($existing = $active_storage->read($name)) {
        $data = $existing;
      }
      else {
        $data = NULL;
      }
    }
    return $data;
  }

  public function filterWrite($name, array $data, StorageInterface $storage) {
    if ($name == 'core.extension') {
      return $this->filterOutIgnored($data, $storage->read($name));
    }

    $dependent_configs = $this->getAllDependentConfigs($storage);
    if (in_array($name, $dependent_configs)) {
      $data = ($existing = $storage->read($name)) ? $existing : NULL;
    }
    return $data;
  }

  public function filterExists($exists, $name, StorageInterface $storage) {
    $active_storage = \Drupal::service('config.storage');
    $dependent_configs = $this->getAllDependentConfigs($storage);
    if (in_array($name, $dependent_configs)) {
      return (bool) $active_storage->read($name);
    }
    return $exists;
  }

  public function filterDelete($doDelete, $name, StorageInterface $storage) {
    $active_storage = \Drupal::service('config.storage');
    $dependent_configs = $this->getAllDependentConfigs($storage);
    if (in_array($name, $dependent_configs)) {
      return (bool) $active_storage->read($name);
    }
    return $doDelete;
  }

  public function rename($new_name, $name, StorageInterface $storage) {
    $dependent_configs = $this->getAllDependentConfigs($storage);
    if (in_array($name, $dependent_configs)) {
      return $name;
    }
    return $new_name;
  }

  public function filterListAll($list, StorageInterface $storage, $prefix = '') {
    $active_storage = \Drupal::service('config.storage');
    $list = array_unique(array_merge($list, $active_storage->listAll($prefix)));
    return $list;
  }

  protected function filterOutIgnored($data, $originalData) {
    foreach ($this->adjustments as $module) {
      if (is_array($originalData) && array_key_exists($module, $originalData['module'])) {
        $data['module'][$module] = $originalData['module'][$module];
      }
      else {
        unset($data['module'][$module]);
      }
    }
    // Make sure data stays sorted so that == comparison works.
    $data['module'] = module_config_sort($data['module']);
    return $data;
  }

  protected function getAllDependentConfigs(StorageInterface $storage) {
    // Find dependent configs in the given storage and in the active storage.
    $active_storage = \Drupal::service('config.storage');
    return array_unique(array_merge(
      $this->getStorageDependentConfigs($storage),
      $this->getStorageDependentConfigs($active_storage)
    ));
  }

  protected function getStorageDependentConfigs(StorageInterface $storage) {
    static $dependents = [];
    $storage_id = spl_object_hash($storage);
    if (!isset($dependents[$storage_id])) {
      // We cannot use the service config.manager because it depends on the
      // active storage.
      $manager = new ConfigManager(
        \Drupal::service('entity.manager'),
        new ConfigFactory(
          $storage,
          \Drupal::service('event_dispatcher'),
          new TypedConfigManager(
            $storage,
            new ExtensionInstallStorage($storage, 'config/schema'),
            \Drupal::service('cache.discovery'),
            \Drupal::service('module_handler')
          )
        ),
        \Drupal::service('config.typed'),
        \Drupal::service('string_translation'),
        $storage,
        \Drupal::service('event_dispatcher')
      );

      // Get configs from other modules which depend on the given modules.
      $external_dependents = array_keys($manager->findConfigEntityDependents('module', $this->adjustments));
      // Get configs from the given modules (which obviously depend on them but
      // are not listed by findConfigEntityDependents().
      $adjustments = $this->adjustments;
      $internal_dependents = array_filter($storage->listAll(), function ($config_name) use ($adjustments) {
        foreach ($this->adjustments as $module_name) {
          if (strpos($config_name, $module_name . '.') === 0) {
            return TRUE;
          }
        }
        return FALSE;
      });
      $dependents[$storage_id] = array_unique(array_merge($internal_dependents, $external_dependents));
    }
    return $dependents[$storage_id];
  }
}
