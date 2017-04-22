<?php
namespace Drush\Commands\pm;


use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Extension\MissingDependencyException;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class PmCommands extends DrushCommands {

  /**
   * Enable one or more modules.
   *
   * @command pm-enable
   * @param $modules A comma delimited list of modules.
   * @aliases en
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @complete \Drush\Commands\CompletionCommands::completeModules
   */
  public function enable($modules) {
    $modules = _convert_csv_to_array($modules);
    $list = $this->addInstallDependencies($modules);
    if (array_values($list) !== $modules) {
      drush_print(dt('The following extensions will be enabled: !list', array('!list' => implode(', ', $list))));
      if(!$this->io()->confirm(dt('Do you want to continue?'))) {
        throw new UserAbortException();
      }
    }
    if (!\Drupal::service('module_installer')->install($modules, TRUE)) {
      throw new \Exception('Unable to install modules.');
    }
    $this->logger()->success(dt('Successfully enabled modules: !list', ['!list' => implode(', ', $list)]));
    // Our logger got blown away during the container rebuild above.
    $boot = \Drush::bootstrapManager()->bootstrap();
    $boot->add_logger();
  }

  /**
   * Uninstall one or more modules and their dependent modules.
   *
   * @command pm-uninstall
   * @param $modules A comma delimited list of modules.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases pmu
   * @complete \Drush\Commands\CompletionCommands::completeModules
   */
  public function uninstall($modules) {
    $modules = _convert_csv_to_array($modules);
    $list = $this->addUninstallDependencies($modules);
    if (array_values($list) !== $modules) {
      drush_print(dt('The following extensions will be uninstalled: !list', array('!list' => implode(', ', $list))));
      if(!$this->io()->confirm(dt('Do you want to continue?'))) {
        throw new UserAbortException();
      }
    }
    if (!\Drupal::service('module_installer')->uninstall($modules, TRUE)) {
      throw new \Exception('Unable to uninstall modules.');
    }
    $this->logger()->success(dt('Successfully uninstalled modules: !list', ['!list' => implode(', ', $list)]));
    // Our logger got blown away during the container rebuild above.
    $boot = \Drush::bootstrapManager()->bootstrap();
    $boot->add_logger();
  }

  /**
   * @hook validate pm-uninstall
   */
  public function validateUninstall(CommandData $commandData) {
    if ($modules = $commandData->input()->getArgument('modules')) {
      $modules = _convert_csv_to_array($modules);
      if ($validation_reasons = \Drupal::service('module_installer')->validateUninstall($modules)) {
        foreach ($validation_reasons as $module => $list) {
          foreach ($list as $markup) {
            $reasons[$module] = "$module: " . (string) $markup;
          }
        }
        throw new \Exception(implode("/n", $reasons));
      }
    }
  }

  /**
   * Show a list of available extensions (modules and themes).
   *
   * @command pm-list
   * @option type Only show extensions having a given type. Choices: module, theme.
   * @option status Only show extensions having a given status. Choices: enabled or disabled.
   * @option core Only show extensions that are in Drupal core.
   * @option no-core Only show extensions that are not provided by Drupal core.
   * @option package Only show extensions having a given project packages (e.g. Development).
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
  public function pmList($options = ['format' => 'table', 'type' => 'module,theme', 'status' => 'enabled,disabled', 'package' => NULL, 'core' => false, 'no-core' => false]) {
    $rows = [];
    $modules = \system_rebuild_module_data();
    $themes = \Drupal::service('theme_handler')->rebuildThemeData();
    $both = array_merge($modules, $themes);

    $package_filter = _convert_csv_to_array(strtolower($options['package']));
    $type_filter = _convert_csv_to_array(strtolower($options['type']));
    $status_filter = _convert_csv_to_array(strtolower($options['status']));

    foreach ($both as $key => $extension) {
      // Filter out test modules/themes.
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

  function addInstallDependencies($modules) {
    $module_data = system_rebuild_module_data();
    $module_list  = array_combine($modules, $modules);
    if ($missing_modules = array_diff_key($module_list, $module_data)) {
      // One or more of the given modules doesn't exist.
      throw new MissingDependencyException(sprintf('Unable to install modules %s due to missing modules %s.', implode(', ', $module_list), implode(', ', $missing_modules)));
    }
    $extension_config = \Drupal::configFactory()->getEditable('core.extension');
    $installed_modules = $extension_config->get('module') ?: array();

    // Copied from \Drupal\Core\Extension\ModuleInstaller::install
    // Add dependencies to the list. The new modules will be processed as
    // the while loop continues.
    while (list($module) = each($module_list)) {
      foreach (array_keys($module_data[$module]->requires) as $dependency) {
        if (!isset($module_data[$dependency])) {
          // The dependency does not exist.
          throw new MissingDependencyException("Unable to install modules: module '$module' is missing its dependency module $dependency.");
        }

        // Skip already installed modules.
        if (!isset($module_list[$dependency]) && !isset($installed_modules[$dependency])) {
          $module_list[$dependency] = $dependency;
        }
      }
    }
    return $module_list;
  }

  function addUninstallDependencies($modules) {
    // Get all module data so we can find dependencies and sort.
    $module_data = system_rebuild_module_data();
    $module_list = array_combine($modules, $modules);
    if ($diff = array_diff_key($module_list, $module_data)) {
      throw new \Exception(dt('A specified extension does not exist: !diff', ['!diff' => implode(',', $diff)]));
    }
    $extension_config = \Drupal::configFactory()->getEditable('core.extension');
    $installed_modules = $extension_config->get('module') ?: array();

    // Add dependent modules to the list. The new modules will be processed as
    // the while loop continues.
    $profile = drupal_get_profile();
    while (list($module) = each($module_list)) {
      foreach (array_keys($module_data[$module]->required_by) as $dependent) {
        if (!isset($module_data[$dependent])) {
          // The dependent module does not exist.
          return FALSE;
        }

        // Skip already uninstalled modules.
        if (isset($installed_modules[$dependent]) && !isset($module_list[$dependent]) && $dependent != $profile) {
          $module_list[$dependent] = $dependent;
        }
      }
    }
    return $module_list;
  }
}
