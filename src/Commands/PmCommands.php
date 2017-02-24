<?php
namespace Drush\Commands;


use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Extension\Extension;

class PmCommands extends DrushCommands {

  /**
   * Enable one or more extensions (modules or themes).
   *
   * @command pm-enable
   * @param $extensions A comma delimited list of modules or themes. You can use the * wildcard at the end of extension names to enable all matches.
   * @aliases en
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function enable($extensions) {
    $extensions = _convert_csv_to_array($extensions);
    if (!\Drupal::service('module_installer')->install($extensions, TRUE)) {
      throw new \Exception('Unable to install modules.');
    }
  }

  /**
   * Uninstall one or more modules and their dependent modules.
   *
   * @command pm-uninstall
   * @param $extensions A comma delimited list of modules.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases pmu
   */
  public function uninstall($extensions) {
    $extensions = _convert_csv_to_array($extensions);
    if (!\Drupal::service('module_installer')->uninstall($extensions, TRUE)) {
      throw new \Exception('Unable to uninstall modules.');
    }
    // Our logger got blown away during the container rebuild above.
    $boot = \Drush::bootstrapManager()->bootstrap();
    $boot->add_logger();
  }

  /**
   * Show a report of available projects and their extensions.
   *
   * @command pm-info
   * @param $extensions A comma delimited list of modules.
   * @option status Filter by project status. Choices: enabled, disabled. A project is considered enabled when at least one of its extensions is enabled.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function info() {

  }

  /**
   * Show a list of available extensions (modules and themes).
   *
   * @command pm-list
   * @param $extensions A comma delimited list of modules.
   * @option type Filter by extension type. Choices: module, theme.
   * @option status Filter by extension status. Choices: enabled or 'not installed'.
   * @option core Filter out extensions that are not in Drupal core.
   * @option no-core Filter out extensions that are provided by Drupal core.
   * @option package Filter by project packages (e.g. Development).
   * @field-labels
   *   package: Package
   *   display_name: Name
   *   name: Name
   *   type: Type
   *   path: Path
   *   status: Status
   *   version: Version
   * @default-fields package,display_name,status,version
   * @aliases pml
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function pmList($extensions = NULL, $options = ['format' => 'table', 'type' => 'module,theme', 'status' => 'enabled,disabled', 'package' => NULL, 'core' => NULL, 'no-core' => NULL]) {
    $rows = [];
    $modules = \system_rebuild_module_data();
    $themes = \Drupal::service('theme_handler')->rebuildThemeData();
    $both = array_merge($modules, $themes);

    $package_filter = _convert_csv_to_array(strtolower($options['package']));
    $type_filter = _convert_csv_to_array(strtolower($options['type']));
    $status_filter = _convert_csv_to_array(strtolower($options['status']));

    foreach ($both as $key => $extension) {
      if (strpos($extension->getPath(), 'tests')) {
        continue;
      }

      $status = $this->extensionStatus($extension);
      if (!in_array($extension->getType(), $type_filter)) {
        unset($modules[$key]);
        continue;
      }
      if (!in_array($status, $status_filter)) {
        unset($modules[$key]);
        continue;
      }

      // Filter out core if --no-core specified.
      if ($options['no-core']) {
        if ($extension->origin == 'core') {
          unset($modules[$key]);
          continue;
        }
      }

      // Filter out non-core if --core specified.
      if ($options['core']) {
        if ($extension->origin != 'core') {
          unset($modules[$key]);
          continue;
        }
      }

      // Filter by package.
      if (!empty($package_filter)) {
        if (!in_array(strtolower($extension->info['package']), $package_filter)) {
          unset($modules[$key]);
          continue;
        }
      }

      $row = [
        'package' => $extension->info['package'],
        'display_name' => $extension->info['name']. ' ('. $extension->getName(). ')',
        'name' => $extension->getName(),
        'type' => $extension->getType(),
        'path' => $extension->getPath(),
        'status' => ucfirst($status),
      // Suppress notice when version is not present.
        'version' => @$extension->info['version'],
      ];
      $rows[$key] = $row;
    }

    return new RowsOfFields($rows);

  }

  /**
   * Calculate an extension status based on current status and schema version.
   *
   * @param $extension
   *   Object of a single extension info.
   *
   * @return
   *   String describing extension status. Values: enabled|disabled.
   */
  function extensionStatus($extension) {
    return $extension->status == 1 ? 'enabled' : 'disabled';
  }




}
