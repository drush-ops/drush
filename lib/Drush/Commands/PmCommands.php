<?php

class PmCommands {

  /**
   * Enable one or more extensions (modules or themes).
   *
   * @command pm-enable
   * @aliases en
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @param string $extensions A list of modules or themes. You can use the * wildcard at the end of extension names to enable all matches.
   * @option $resolve-dependencies Attempt to download any missing dependencies. At the moment, only works when the module name is the same as the project name.
   * @option $skip Skip automatic downloading of libraries (c.f. devel).
   */
  public function enable($extensions = '', $options = ['resolve-dependencies' => '', 'skip' => '']) {
    // Get the data built during the validate phase
    $extension_info = drush_get_context('PM_ENABLE_EXTENSION_INFO');
    $modules = drush_get_context('PM_ENABLE_MODULES');
    $themes = drush_get_context('PM_ENABLE_THEMES');
  
    // Inform the user which extensions will finally be enabled.
    $extensions = array_merge($modules, $themes);
    if (empty($extensions)) {
      return drush_log(dt('There were no extensions that could be enabled.'), LogLevel::OK);
    }
    else {
      drush_print(dt('The following extensions will be enabled: !extensions', array('!extensions' => implode(', ', $extensions))));
      if(!drush_confirm(dt('Do you really want to continue?'))) {
        return drush_user_abort();
      }
    }
  
    // Enable themes.
    if (!empty($themes)) {
      drush_theme_enable($themes);
    }
  
    // Enable modules and pass dependency validation in form submit.
    if (!empty($modules)) {
      drush_include_engine('drupal', 'environment');
      drush_module_enable($modules);
    }
  
    // Inform the user of final status.
    $result_extensions = drush_get_named_extensions_list($extensions);
    $problem_extensions = array();
    $role = RoleCommands::get_instance();
    foreach ($result_extensions as $name => $extension) {
      if ($extension->status) {
        drush_log(dt('!extension was enabled successfully.', array('!extension' => $name)), LogLevel::OK);
        $perms = $role->getModulePerms($name);
        if (!empty($perms)) {
          drush_print(dt('!extension defines the following permissions: !perms', array('!extension' => $name, '!perms' => implode(', ', $perms))));
        }
      }
      else {
        $problem_extensions[] = $name;
      }
    }
    if (!empty($problem_extensions)) {
      return drush_set_error('DRUSH_PM_ENABLE_EXTENSION_ISSUE', dt('There was a problem enabling !extension.', array('!extension' => implode(',', $problem_extensions))));
    }
    // Return the list of extensions enabled
    return $extensions;

  }

  /**
   * Disable one or more extensions (modules or themes).
   *
   * @command pm-disable
   * @aliases dis
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @param string $extensions A list of modules or themes. You can use the * wildcard at the end of extension names to disable multiple matches.
   * @option $version-control=backup Backup all project files before updates.
   * @option $version-control=bzr Quickly add/remove/commit your project changes to Bazaar.
   * @option $version-control=svn Quickly add/remove/commit your project changes to Subversion.
   * @option $cache Cache release XML and tarballs or git clones. Git clones use git's --reference option. Defaults to 1 for downloads, and 0 for git.
   * @option $package-handler=wget Download project packages using wget or curl.
   * @option $package-handler=git_drupalorg Use git.drupal.org to checkout and update projects.
   */
  public function disable($extensions = '', $options = ['version-control=backup' => '', 'version-control=bzr' => '', 'version-control=svn' => '', 'cache' => '', 'package-handler=wget' => '', 'package-handler=git_drupalorg' => '']) {
    $args = pm_parse_arguments(func_get_args());
    drush_include_engine('drupal', 'pm');
    _drush_pm_disable($args);

  }

  /**
   * Show detailed info for one or more extensions (modules or themes).
   *
   * @command pm-info
   * @aliases pmi
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @param string $extensions A list of modules or themes. You can use the * wildcard at the end of extension names to show info for multiple matches. If no argument is provided it will show info for all available extensions.
   * @option $format Select output format. Available: csv, html, json, list, table, var_export, yaml. Default is key-value-list.
   * @option $fields Fields to output.
   * @option $list-separator Specify how elements in a list should be separated. In lists of lists, this applies to the elements in the inner lists.
   * @option $line-separator In nested lists of lists, specify how the outer lists ("lines") should be separated.
   * @option $field-labels Add field labels before first line of data. Default is on; use --no-field-labels to disable.
   * @option $format=config A configuration file in executable php format. The variable name is "config", and the variable keys are taken from the output data array's keys.
   * @option $format=csv A list of values, one per row, each of which is a comma-separated list of values.
   * @option $format=html An HTML representation
   * @option $format=json Javascript Object Notation.
   * @option $format=labeled-export A list of php exports, labeled with a name.
   * @option $format=list A simple list of values.
   * @option $format=php A serialized php string.
   * @option $format=print-r Output via php print_r function.
   * @option $format=table A formatted, word-wrapped table.
   * @option $format=var_export An array in executable php format.
   * @option $format=variables A list of php variable assignments.
   * @option $format=yaml Yaml output format.
   * @field-labels
   *   extension: Extension
   *   project: Project
   *   type: Type
   *   title: Title
   *   description: Description
   *   version: Version
   *   date: Date
   *   package: Package
   *   core: Core
   *   php: PHP
   *   status: Status
   *   path: Path
   *   schema_version: Schema version
   *   files: Files
   *   requires: Requires
   *   required_by: Required by
   *   permissions: Permissions
   *   config: Configure
   *   engine: Engine
   *   base_theme: Base theme
   *   regions: Regions
   *   features: Features
   *   stylesheets: Stylesheets
   *   scripts: Scripts
   * @pipe-format json
   */
  public function info($extensions = '', $options = ['format' => '', 'fields' => '', 'list-separator' => '', 'line-separator' => '', 'field-labels' => '', 'format=config' => '', 'format=csv' => '', 'format=html' => '', 'format=json' => '', 'format=labeled-export' => '', 'format=list' => '', 'format=php' => '', 'format=print-r' => '', 'format=table' => '', 'format=var_export' => '', 'format=variables' => '', 'format=yaml' => '']) {
    $result = array();
    $args = pm_parse_arguments(func_get_args());
  
    $extension_info = drush_get_extensions(FALSE);
    _drush_pm_expand_extensions($args, $extension_info);
    // If no extensions are provided, show all.
    if (count($args) == 0) {
      $args = array_keys($extension_info);
    }
  
    foreach ($args as $extension) {
      if (isset($extension_info[$extension])) {
        $info = $extension_info[$extension];
      }
      else {
        drush_log(dt('!extension was not found.', array('!extension' => $extension)), LogLevel::WARNING);
        continue;
      }
      if (drush_extension_get_type($info) == 'module') {
        $data = _drush_pm_info_module($info);
      }
      else {
        $data = _drush_pm_info_theme($info);
      }
      $result[$extension] = $data;
    }
    return $result;

  }

  /**
   * Show a report of available projects and their extensions.
   *
   * @command pm-projectinfo
   * @aliases pmpi
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @param string $projects Optional. A list of installed projects to show.
   * @option $drush Optional. Only incude projects that have one or more Drush commands.
   * @option $status Filter by project status. Choices: enabled, disabled. A project is considered enabled when at least one of its extensions is enabled.
   * @option $format Select output format. Available: csv, html, json, list, table, var_export, yaml. Default is key-value-list.
   * @option $fields Fields to output.
   * @option $list-separator Specify how elements in a list should be separated. In lists of lists, this applies to the elements in the inner lists.
   * @option $line-separator In nested lists of lists, specify how the outer lists ("lines") should be separated.
   * @option $field-labels Add field labels before first line of data. Default is on; use --no-field-labels to disable.
   * @option $format=config A configuration file in executable php format. The variable name is "config", and the variable keys are taken from the output data array's keys.
   * @option $format=csv A list of values, one per row, each of which is a comma-separated list of values.
   * @option $format=html An HTML representation
   * @option $format=json Javascript Object Notation.
   * @option $format=labeled-export A list of php exports, labeled with a name.
   * @option $format=list A simple list of values.
   * @option $format=php A serialized php string.
   * @option $format=print-r Output via php print_r function.
   * @option $format=table A formatted, word-wrapped table.
   * @option $format=var_export An array in executable php format.
   * @option $format=variables A list of php variable assignments.
   * @option $format=yaml Yaml output format.
   * @field-labels
   *   label: Name
   *   type: Type
   *   version: Version
   *   status: Status
   *   extensions: Extensions
   *   drush: Drush Commands
   *   datestamp: Datestamp
   *   path: Path
   * @default-fields label,type,version,status,extensions,drush,datestamp,path
   * @pipe-format json
   */
  public function projectinfo($projects = '', $options = ['drush' => '', 'status' => '', 'format' => '', 'fields' => '', 'list-separator' => '', 'line-separator' => '', 'field-labels' => '', 'format=config' => '', 'format=csv' => '', 'format=html' => '', 'format=json' => '', 'format=labeled-export' => '', 'format=list' => '', 'format=php' => '', 'format=print-r' => '', 'format=table' => '', 'format=var_export' => '', 'format=variables' => '', 'format=yaml' => '']) {
    // Get specific requests.
    $requests = pm_parse_arguments(func_get_args(), FALSE);
  
    // Get installed extensions and projects.
    $extensions = drush_get_extensions();
    $projects = drush_get_projects($extensions);
  
    // If user did not specify any projects, return them all
    if (empty($requests)) {
      $result = $projects;
    }
    else {
      $result = array();
      foreach ($requests as $name) {
        if (array_key_exists($name, $projects)) {
          $result[$name] = $projects[$name];
        }
        else {
          drush_log(dt('!project was not found.', array('!project' => $name)), LogLevel::WARNING);
          continue;
        }
      }
    }
  
    // Find the Drush commands that belong with each project.
    foreach ($result as $name => $project) {
      $drush_commands = pm_projectinfo_commands_in_project($project);
      if (!empty($drush_commands)) {
        $result[$name]['drush'] = $drush_commands;
      }
    }
  
    // If user specified --drush, remove projects with no drush extensions
    if (drush_get_option('drush')) {
      foreach ($result as $name => $project) {
        if (!array_key_exists('drush', $project)) {
          unset($result[$name]);
        }
      }
    }
  
    // If user specified --status=1|0, remove projects with a distinct status.
    if (($status = drush_get_option('status', FALSE)) !== FALSE) {
      $status_code = ($status == 'enabled') ? 1 : 0;
      foreach ($result as $name => $project) {
        if ($project['status'] != $status_code) {
          unset($result[$name]);
        }
      }
    }
  
    return $result;

  }

  /**
   * Uninstall one or more modules.
   *
   * @command pm-uninstall
   * @aliases pmu
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @param string $modules A list of modules.
   */
  public function uninstall($modules = '') {
    $args = pm_parse_arguments(func_get_args());
    drush_include_engine('drupal', 'pm');
    _drush_pm_uninstall($args);

  }

  /**
   * Show a list of available extensions (modules and themes).
   *
   * @command pm-list
   * @aliases pml
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @option $type Filter by extension type. Choices: module, theme.
   * @option $status Filter by extension status. Choices: enabled, disabled and/or 'not installed'. You can use multiple comma separated values. (i.e. --status="disabled,not installed").
   * @option $package Filter by project packages. You can use multiple comma separated values. (i.e. --package="Core - required,Other").
   * @option $core Filter out extensions that are not in drupal core.
   * @option $no-core Filter out extensions that are provided by drupal core.
   * @option $format Select output format. Available: table, csv, html, json, list, var_export, yaml. Default is table.
   * @option $fields Fields to output.
   * @option $list-separator Specify how elements in a list should be separated. In lists of lists, this applies to the elements in the inner lists.
   * @option $line-separator In nested lists of lists, specify how the outer lists ("lines") should be separated.
   * @option $field-labels Add field labels before first line of data. Default is on; use --no-field-labels to disable.
   * @option $format=table A formatted, word-wrapped table.
   * @option $format=config A configuration file in executable php format. The variable name is "config", and the variable keys are taken from the output data array's keys.
   * @option $format=csv A list of values, one per row, each of which is a comma-separated list of values.
   * @option $format=html An HTML representation
   * @option $format=json Javascript Object Notation.
   * @option $format=labeled-export A list of php exports, labeled with a name.
   * @option $format=list A simple list of values.
   * @option $format=php A serialized php string.
   * @option $format=print-r Output via php print_r function.
   * @option $format=var_export An array in executable php format.
   * @option $format=variables A list of php variable assignments.
   * @option $format=yaml Yaml output format.
   * @field-labels
   *   package: Package
   *   name: Name
   *   type: Type
   *   status: Status
   *   version: Version
   * @pipe-format list
   */
  public function list($options = ['type' => '', 'status' => '', 'package' => '', 'core' => '', 'no-core' => '', 'format' => '', 'fields' => '', 'list-separator' => '', 'line-separator' => '', 'field-labels' => '', 'format=table' => '', 'format=config' => '', 'format=csv' => '', 'format=html' => '', 'format=json' => '', 'format=labeled-export' => '', 'format=list' => '', 'format=php' => '', 'format=print-r' => '', 'format=var_export' => '', 'format=variables' => '', 'format=yaml' => '']) {
    //--package
    $package_filter = array();
    $package = strtolower(drush_get_option('package'));
    if (!empty($package)) {
      $package_filter = explode(',', $package);
    }
    if (!empty($package_filter) && (count($package_filter) == 1)) {
      drush_hide_output_fields('package');
    }
  
    //--type
    $all_types = array('module', 'theme');
    $type_filter = strtolower(drush_get_option('type'));
    if (!empty($type_filter)) {
      $type_filter = explode(',', $type_filter);
    }
    else {
      $type_filter = $all_types;
    }
  
    if (count($type_filter) == 1) {
      drush_hide_output_fields('type');
    }
    foreach ($type_filter as $type) {
      if (!in_array($type, $all_types)) { //TODO: this kind of check can be implemented drush-wide
        return drush_set_error('DRUSH_PM_INVALID_PROJECT_TYPE', dt('!type is not a valid project type.', array('!type' => $type)));
      }
    }
  
    //--status
    $all_status = array('enabled', 'disabled', 'not installed');
    $status_filter = strtolower(drush_get_option('status'));
    if (!empty($status_filter)) {
      $status_filter = explode(',', $status_filter);
    }
    else {
      $status_filter = $all_status;
    }
    if (count($status_filter) == 1) {
      drush_hide_output_fields('status');
    }
  
    foreach ($status_filter as $status) {
      if (!in_array($status, $all_status)) { //TODO: this kind of check can be implemented drush-wide
        return drush_set_error('DRUSH_PM_INVALID_PROJECT_STATUS', dt('!status is not a valid project status.', array('!status' => $status)));
    }
    }
  
    $result = array();
    $extension_info = drush_get_extensions(FALSE);
    uasort($extension_info, '_drush_pm_sort_extensions');
  
    $major_version = drush_drupal_major_version();
    foreach ($extension_info as $key => $extension) {
      if (!in_array(drush_extension_get_type($extension), $type_filter)) {
        unset($extension_info[$key]);
        continue;
      }
      $status = drush_get_extension_status($extension);
      if (!in_array($status, $status_filter)) {
        unset($extension_info[$key]);
        continue;
      }
  
      // Filter out core if --no-core specified.
      if (drush_get_option('no-core', FALSE)) {
        if ((($major_version >= 8) && ($extension->origin == 'core')) || (($major_version <= 7) && (strpos($extension->info['package'], 'Core') === 0))) {
          unset($extension_info[$key]);
          continue;
        }
      }
  
      // Filter out non-core if --core specified.
      if (drush_get_option('core', FALSE)) {
        if ((($major_version >= 8) && ($extension->origin != 'core')) || (($major_version <= 7) && (strpos($extension->info['package'], 'Core') !== 0))) {
          unset($extension_info[$key]);
          continue;
        }
      }
  
      // Filter by package.
      if (!empty($package_filter)) {
        if (!in_array(strtolower($extension->info['package']), $package_filter)) {
          unset($extension_info[$key]);
          continue;
        }
      }
  
      $row['package'] = $extension->info['package'];
      $row['name'] = $extension->label;
      $row['type'] = ucfirst(drush_extension_get_type($extension));
      $row['status'] = ucfirst($status);
      // Suppress notice when version is not present.
      $row['version'] = @$extension->info['version'];
  
      $result[$key] = $row;
      unset($row);
    }
    // In Drush-5, we used to return $extension_info here.
    return $result;

  }

  /**
   * Refresh update status information.
   *
   * @command pm-refresh
   * @aliases rf
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @option $update-backend Backend to obtain available updates. Available: drush, drupal. Default is drush.
   * @option $check-disabled Check for updates of disabled modules and themes.
   * @option $security-only Only update modules that have security updates available.
   * @option $update-backend=drush Check available updates without update.module.
   * @option $update-backend=drupal Check available updates with update.module.
   */
  public function refresh($options = ['update-backend' => '', 'check-disabled' => '', 'security-only' => '', 'update-backend=drush' => '', 'update-backend=drupal' => '']) {
    $update_status = drush_get_engine('update_status');
    drush_print(dt("Refreshing update status information ..."));
    $update_status->refresh();
    drush_print(dt("Done."));

  }

  /**
   * Show a report of available minor updates to Drupal core and contrib projects.
   *
   * @command pm-updatestatus
   * @aliases ups
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @param string $projects Optional. A list of installed projects to show.
   * @option $pipe Return a list of the projects with any extensions enabled that need updating, one project per line.
   * @option $lock Add a persistent lock to remove the specified projects from consideration during updates.  Locks may be removed with the --unlock parameter, or overridden by specifically naming the project as a parameter to pm-update or pm-updatecode.  The lock does not affect pm-download.  See also the update_advanced project for similar and improved functionality.
   * @option $update-backend Backend to obtain available updates. Available: drush, drupal. Default is drush.
   * @option $check-disabled Check for updates of disabled modules and themes.
   * @option $security-only Only update modules that have security updates available.
   * @option $update-backend=drush Check available updates without update.module.
   * @option $update-backend=drupal Check available updates with update.module.
   * @option $format Select output format. Available: table, csv, html, json, list, var_export, yaml. Default is table.
   * @option $fields Fields to output.
   * @option $list-separator Specify how elements in a list should be separated. In lists of lists, this applies to the elements in the inner lists.
   * @option $line-separator In nested lists of lists, specify how the outer lists ("lines") should be separated.
   * @option $field-labels Add field labels before first line of data. Default is on; use --no-field-labels to disable.
   * @option $format=table A formatted, word-wrapped table.
   * @option $format=config A configuration file in executable php format. The variable name is "config", and the variable keys are taken from the output data array's keys.
   * @option $format=csv A list of values, one per row, each of which is a comma-separated list of values.
   * @option $format=html An HTML representation
   * @option $format=json Javascript Object Notation.
   * @option $format=labeled-export A list of php exports, labeled with a name.
   * @option $format=list A simple list of values.
   * @option $format=php A serialized php string.
   * @option $format=print-r Output via php print_r function.
   * @option $format=var_export An array in executable php format.
   * @option $format=variables A list of php variable assignments.
   * @option $format=yaml Yaml output format.
   * @field-labels
   *   name: Short Name
   *   label: Name
   *   existing_version: Installed Version
   *   status: Status
   *   status_msg: Message
   *   candidate_version: Proposed version
   * @default-fields label,existing_version,candidate_version,status_msg
   * @pipe-format list
   */
  public function updatestatus($projects = '', $options = ['pipe' => '', 'lock' => '', 'update-backend' => '', 'check-disabled' => '', 'security-only' => '', 'update-backend=drush' => '', 'update-backend=drupal' => '', 'format' => '', 'fields' => '', 'list-separator' => '', 'line-separator' => '', 'field-labels' => '', 'format=table' => '', 'format=config' => '', 'format=csv' => '', 'format=html' => '', 'format=json' => '', 'format=labeled-export' => '', 'format=list' => '', 'format=php' => '', 'format=print-r' => '', 'format=var_export' => '', 'format=variables' => '', 'format=yaml' => '']) {
    // Get specific requests.
    $args = pm_parse_arguments(func_get_args(), FALSE);
  
    // Get installed extensions and projects.
    $extensions = drush_get_extensions();
    $projects = drush_get_projects($extensions);
  
    // Parse out project name and version.
    $requests = array();
    foreach ($args as $request) {
      $request = pm_parse_request($request, NULL, $projects);
      $requests[$request['name']] = $request;
    }
  
    // Get the engine instance.
    $update_status = drush_get_engine('update_status');
  
    // If the user doesn't provide a value for check-disabled option,
    // and the update backend is 'drupal', use NULL, so the engine
    // will respect update.module defaults.
    $check_disabled_default = ($update_status->engine == 'drupal') ? NULL : FALSE;
    $check_disabled = drush_get_option('check-disabled', $check_disabled_default);
  
    $update_info = $update_status->getStatus($projects, $check_disabled);
  
    foreach ($extensions as $name => $extension) {
      // Add an item to $update_info for each enabled extension which was obtained
      // from cvs or git and its project is unknown (because of cvs_deploy or
      // git_deploy is not enabled).
      if (!isset($extension->info['project'])) {
        if ((isset($extension->vcs)) && ($extension->status)) {
          $update_info[$name] = array(
            'name' => $name,
            'label' => $extension->label,
            'existing_version' => 'Unknown',
            'status' => DRUSH_UPDATESTATUS_PROJECT_NOT_PACKAGED,
            'status_msg' => dt('Project was not packaged by drupal.org but obtained from !vcs. You need to enable !vcs_deploy module', array('!vcs' => $extension->vcs)),
          );
          // The user may have requested to update a project matching this
          // extension. If it was by coincidence or error we don't mind as we've
          // already added an item to $update_info. Just clean up $requests.
          if (isset($requests[$name])) {
            unset($requests[$name]);
          }
        }
      }
      // Additionally if the extension name is distinct to the project name and
      // the user asked to update the extension, fix the request.
      elseif ((isset($requests[$name])) && ($name != $extension->info['project'])) {
        $requests[$extension->info['project']] = $requests[$name];
        unset($requests[$name]);
      }
    }
    // If specific project updates were requested then remove releases for all
    // others.
    $requested = func_get_args();
    if (!empty($requested)) {
      foreach ($update_info as $name => $project) {
        if (!isset($requests[$name])) {
          unset($update_info[$name]);
        }
      }
    }
    // Add an item to $update_info for each request not present in $update_info.
    foreach ($requests as $name => $request) {
      if (!isset($update_info[$name])) {
        // Disabled projects.
        if ((isset($projects[$name])) && ($projects[$name]['status'] == 0)) {
          $update_info[$name] = array(
            'name' => $name,
            'label' => $projects[$name]['label'],
            'existing_version' => $projects[$name]['version'],
            'status' => DRUSH_UPDATESTATUS_REQUESTED_PROJECT_NOT_UPDATEABLE,
          );
          unset($requests[$name]);
        }
        // At this point we are unable to find matching installed project.
        // It does not exist at all or it is misspelled,...
        else {
          $update_info[$name] = array(
            'name' => $name,
            'label' => $name,
            'existing_version' => 'Unknown',
            'status'=> DRUSH_UPDATESTATUS_REQUESTED_PROJECT_NOT_FOUND,
          );
        }
      }
    }
  
    // If specific versions were requested, match the requested release.
    foreach ($requests as $name => $request) {
      if (!empty($request['version'])) {
        if (empty($update_info[$name]['releases'][$request['version']])) {
          $update_info[$name]['status'] = DRUSH_UPDATESTATUS_REQUESTED_VERSION_NOT_FOUND;
        }
        elseif ($request['version'] == $update_info[$name]['existing_version']) {
          $update_info[$name]['status'] = DRUSH_UPDATESTATUS_REQUESTED_VERSION_CURRENT;
        }
        // TODO: should we warn/reject if this is a downgrade?
        else {
          $update_info[$name]['status'] = DRUSH_UPDATESTATUS_REQUESTED_VERSION_NOT_CURRENT;
          $update_info[$name]['candidate_version'] = $request['version'];
        }
      }
    }
    // Process locks specified on the command line.
    $locked_list = drush_pm_update_lock($update_info, drush_get_option_list('lock'), drush_get_option_list('unlock'), drush_get_option('lock-message'));
  
    // Build project updatable messages, set candidate version and mark
    // 'updateable' in the project.
    foreach ($update_info as $key => $project) {
      switch($project['status']) {
        case DRUSH_UPDATESTATUS_NOT_SECURE:
          $status = dt('SECURITY UPDATE available');
          pm_release_recommended($project);
          break;
        case DRUSH_UPDATESTATUS_REVOKED:
          $status = dt('Installed version REVOKED');
          pm_release_recommended($project);
          break;
        case DRUSH_UPDATESTATUS_NOT_SUPPORTED:
          $status = dt('Installed version not supported');
          pm_release_recommended($project);
          break;
        case DRUSH_UPDATESTATUS_NOT_CURRENT:
          $status = dt('Update available');
          pm_release_recommended($project);
          break;
        case DRUSH_UPDATESTATUS_CURRENT:
          $status = dt('Up to date');
          pm_release_recommended($project);
          $project['updateable'] = FALSE;
          break;
        case DRUSH_UPDATESTATUS_NOT_CHECKED:
        case DRUSH_UPDATESTATUS_NOT_FETCHED:
        case DRUSH_UPDATESTATUS_FETCH_PENDING:
          $status = dt('Unable to check status');
          break;
        case DRUSH_UPDATESTATUS_PROJECT_NOT_PACKAGED:
          $status = $project['status_msg'];
          break;
        case DRUSH_UPDATESTATUS_REQUESTED_PROJECT_NOT_UPDATEABLE:
          $status = dt('Project has no enabled extensions and can\'t be updated');
          break;
        case DRUSH_UPDATESTATUS_REQUESTED_PROJECT_NOT_FOUND:
          $status = dt('Specified project not found');
          break;
        case DRUSH_UPDATESTATUS_REQUESTED_VERSION_NOT_FOUND:
          $status = dt('Specified version not found');
          break;
        case DRUSH_UPDATESTATUS_REQUESTED_VERSION_CURRENT:
          $status = dt('Specified version already installed');
          break;
        case DRUSH_UPDATESTATUS_REQUESTED_VERSION_NOT_CURRENT:
          $status = dt('Specified version available');
          $project['updateable'] = TRUE;
          break;
        default:
          $status = dt('Unknown');
          break;
      }
  
      if (isset($project['locked'])) {
        $status = $project['locked'] . " ($status)";
      }
      // Persist candidate_version in $update_info (plural).
      if (empty($project['candidate_version'])) {
        $update_info[$key]['candidate_version'] = $project['existing_version']; // Default to no change
      }
      else {
        $update_info[$key]['candidate_version'] = $project['candidate_version'];
      }
      $update_info[$key]['status_msg'] = $status;
      if (isset($project['updateable'])) {
        $update_info[$key]['updateable'] = $project['updateable'];
      }
    }
  
    // Filter projects to show.
    return pm_project_filter($update_info, drush_get_option('security-only'));

  }

  /**
   * Update Drupal core and contrib projects to latest recommended releases.
   *
   * @command pm-updatecode
   * @aliases upc
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   * @param string $projects Optional. A list of installed projects to update.
   * @option $notes Show release notes for each project to be updated.
   * @option $no-core Only update modules and skip the core update.
   * @option $check-updatedb Check to see if an updatedb is needed after updating the code. Default is on; use --check-updatedb=0 to disable.
   * @option $lock Add a persistent lock to remove the specified projects from consideration during updates.  Locks may be removed with the --unlock parameter, or overridden by specifically naming the project as a parameter to pm-update or pm-updatecode.  The lock does not affect pm-download.  See also the update_advanced project for similar and improved functionality.
   * @option $version-control=backup Backup all project files before updates.
   * @option $version-control=bzr Quickly add/remove/commit your project changes to Bazaar.
   * @option $version-control=svn Quickly add/remove/commit your project changes to Subversion.
   * @option $cache Cache release XML and tarballs or git clones. Git clones use git's --reference option. Defaults to 1 for downloads, and 0 for git.
   * @option $package-handler=wget Download project packages using wget or curl.
   * @option $package-handler=git_drupalorg Use git.drupal.org to checkout and update projects.
   * @option $update-backend Backend to obtain available updates. Available: drush, drupal. Default is drush.
   * @option $check-disabled Check for updates of disabled modules and themes.
   * @option $security-only Only update modules that have security updates available.
   * @option $update-backend=drush Check available updates without update.module.
   * @option $update-backend=drupal Check available updates with update.module.
   * @usage drush pm-updatecode --no-core
   *   Update contrib projects, but skip core.
   * @usage drush pm-updatestatus --format=csv --list-separator=" " --fields="name,existing_version,candidate_version,status_msg"
   *   To show a list of projects with their update status, use pm-updatestatus instead of pm-updatecode.
   */
  public function updatecode($projects = '', $options = ['notes' => '', 'no-core' => '', 'check-updatedb' => '', 'lock' => '', 'version-control=backup' => '', 'version-control=bzr' => '', 'version-control=svn' => '', 'cache' => '', 'package-handler=wget' => '', 'package-handler=git_drupalorg' => '', 'update-backend' => '', 'check-disabled' => '', 'security-only' => '', 'update-backend=drush' => '', 'update-backend=drupal' => '']) {
    // In --pipe mode, just run pm-updatestatus and exit.
    if (drush_get_context('DRUSH_PIPE')) {
      drush_set_option('strict', 0);
      return drush_invoke('pm-updatestatus');
    }
  
    $update_status = drush_get_engine('update_status');
  
    // Get specific requests.
    $requests = pm_parse_arguments(func_get_args(), FALSE);
  
    // Print report of modules to update, and record
    // result of that function in $update_info.
    $updatestatus_options = array();
    foreach (array('lock', 'unlock', 'lock-message', 'update-backend', 'check-disabled', 'security-only') as $option) {
      $value = drush_get_option($option, FALSE);
      if ($value) {
        $updatestatus_options[$option] = $value;
      }
    }
    $backend_options = array(
      'integrate' => FALSE,
    );
    $values = drush_invoke_process("@self", 'pm-updatestatus', func_get_args(), $updatestatus_options, $backend_options);
    if (!is_array($values) || $values['error_status']) {
      return drush_set_error('pm-updatestatus failed.');
    }
    $last = $update_status->lastCheck();
    drush_print(dt('Update information last refreshed: ') . ($last  ? format_date($last) : dt('Never')));
    drush_print($values['output']);
  
    $update_info = $values['object'];
  
    // Prevent update of core if --no-core was specified.
    if (isset($update_info['drupal']) && drush_get_option('no-core', FALSE)) {
      unset($update_info['drupal']);
      drush_print(dt('Skipping core update (--no-core specified).'));
    }
  
    // Remove locked and non-updateable projects.
    foreach ($update_info as $name => $project) {
      if ((isset($project['locked']) && !isset($requests[$name])) || (!isset($project['updateable']) || !$project['updateable'])) {
        unset($update_info[$name]);
      }
    }
  
    // Do no updates in simulated mode.
    if (drush_get_context('DRUSH_SIMULATE')) {
      return drush_log(dt('No action taken in simulated mode.'), LogLevel::OK);
      return TRUE;
    }
  
    $tmpfile = drush_tempnam('pm-updatecode.');
  
    $core_update_available = FALSE;
    if (isset($update_info['drupal'])) {
      $drupal_project = $update_info['drupal'];
      unset($update_info['drupal']);
  
      // At present we need to update drupal core after non-core projects
      // are updated.
      if (empty($update_info)) {
        return _pm_update_core($drupal_project, $tmpfile);
      }
      // If there are modules other than drupal core enabled, then update them
      // first.
      else {
        $core_update_available = TRUE;
        if ($drupal_project['status'] == DRUSH_UPDATESTATUS_NOT_SECURE) {
          drush_print(dt("NOTE: A security update for the Drupal core is available."));
        }
        else {
          drush_print(dt("NOTE: A code update for the Drupal core is available."));
        }
        drush_print(dt("Drupal core will be updated after all of the non-core projects are updated.\n"));
      }
    }
  
    // If there are no releases to update, then print a final
    // exit message.
    if (empty($update_info)) {
      if (drush_get_option('security-only')) {
        return drush_log(dt('No security updates available.'), LogLevel::OK);
      }
      else {
        return drush_log(dt('No code updates available.'), LogLevel::OK);
      }
    }
  
    // Offer to update to the identified releases.
    if (!pm_update_packages($update_info, $tmpfile)) {
      return FALSE;
    }
  
    // After projects are updated we can update core.
    if ($core_update_available) {
      drush_print();
      return _pm_update_core($drupal_project, $tmpfile);
    }

  }

  /**
   * Update Drupal core and contrib projects and apply any pending database updates (Same as pm-updatecode + updatedb).
   *
   * @command pm-update
   * @aliases up
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   */
  public function update() {
    // Call pm-updatecode.  updatedb will be called in the post-update process.
    $args = pm_parse_arguments(func_get_args(), FALSE);
    drush_set_option('check-updatedb', FALSE);
    return drush_invoke('pm-updatecode', $args);

  }

  /**
   * Notify of pending db updates.
   *
   * @command pm-updatecode-postupdate
   * @aliases 
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   *
   */
  public function updatecode_postupdate() {
    // Clear the cache, since some projects could have moved around.
    drush_drupal_cache_clear_all();
  
    // Notify of pending database updates.
    // Make sure the installation API is available
    require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
  
    // Load all .install files.
    drupal_load_updates();
  
    // @see system_requirements().
    foreach (drush_module_list() as $module) {
      $updates = drupal_get_schema_versions($module);
      if ($updates !== FALSE) {
        $default = drupal_get_installed_schema_version($module);
        if (max($updates) > $default) {
          drush_log(dt("You have pending database updates. Run `drush updatedb` or visit update.php in your browser."), LogLevel::WARNING);
          break;
        }
      }
    }

  }

  /**
   * Print release notes for given projects.
   *
   * @command pm-releasenotes
   * @aliases rln
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   *
   * @param string $projects A list of project names, with optional version. Defaults to 'drupal'
   * @option $html Display release notes in HTML rather than plain text.
   * @option $source The base URL which provides project release history in XML. Defaults to http://updates.drupal.org/release-history.
   * @option $dev Work with development releases solely.
   * @usage drush rln cck
   *   Prints the release notes for the recommended version of CCK project.
   * @usage drush rln token-1.13
   *   View release notes of a specfic version of the Token project for my version of Drupal.
   * @usage drush rln pathauto zen
   *   View release notes for the recommended version of Pathauto and Zen projects.
   */
  public function releasenotes($projects = '', $options = ['html' => '', 'source' => '', 'dev' => '']) {
    $release_info = drush_get_engine('release_info');
  
    // Obtain requests.
    if (!$requests = pm_parse_arguments(func_get_args(), FALSE)) {
      $requests = array('drupal');
    }
  
    // Get installed projects.
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
      $projects = drush_get_projects();
    }
    else {
      $projects = array();
    }
  
    $status_url = drush_get_option('source');
  
    $output = '';
    foreach($requests as $request) {
      $request = pm_parse_request($request, $status_url, $projects);
      $project_release_info = $release_info->get($request);
      if ($project_release_info) {
        $version = empty($request['version']) ? NULL : $request['version'];
        $output .= $project_release_info->getReleaseNotes($version);
      }
    }
    return $output;

  }

  /**
   * Print release information for given projects.
   *
   * @command pm-releases
   * @aliases rl
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   *
   * @param string $projects A list of drupal.org project names. Defaults to 'drupal'
   * @option $default-major Show releases compatible with the specified major version of Drupal.
   * @option $source The base URL which provides project release history in XML. Defaults to http://updates.drupal.org/release-history.
   * @option $dev Work with development releases solely.
   * @option $format Select output format. Available: table, csv, html, json, list, var_export, yaml. Default is table.
   * @option $fields Fields to output.
   * @option $list-separator Specify how elements in a list should be separated. In lists of lists, this applies to the elements in the inner lists.
   * @option $line-separator In nested lists of lists, specify how the outer lists ("lines") should be separated.
   * @option $field-labels Add field labels before first line of data. Default is on; use --no-field-labels to disable.
   * @option $format=table A formatted, word-wrapped table.
   * @option $format=config A configuration file in executable php format. The variable name is "config", and the variable keys are taken from the output data array's keys.
   * @option $format=csv A list of values, one per row, each of which is a comma-separated list of values.
   * @option $format=html An HTML representation
   * @option $format=json Javascript Object Notation.
   * @option $format=labeled-export A list of php exports, labeled with a name.
   * @option $format=list A simple list of values.
   * @option $format=php A serialized php string.
   * @option $format=print-r Output via php print_r function.
   * @option $format=var_export An array in executable php format.
   * @option $format=variables A list of php variable assignments.
   * @option $format=yaml Yaml output format.
   * @usage drush pm-releases cck zen
   *   View releases for cck and Zen projects for your Drupal version.
   * @field-labels
   *   project: Project
   *   version: Release
   *   date: Date
   *   status: Status
   *   release_link: Release link
   *   download_link: Download link
   * @default-fields project,version,date,status
   * @pipe-format csv
   */
  public function releases($projects = '', $options = ['default-major' => '', 'source' => '', 'dev' => '', 'format' => '', 'fields' => '', 'list-separator' => '', 'line-separator' => '', 'field-labels' => '', 'format=table' => '', 'format=config' => '', 'format=csv' => '', 'format=html' => '', 'format=json' => '', 'format=labeled-export' => '', 'format=list' => '', 'format=php' => '', 'format=print-r' => '', 'format=var_export' => '', 'format=variables' => '', 'format=yaml' => '']) {
    $release_info = drush_get_engine('release_info');
  
    // Obtain requests.
    $requests = pm_parse_arguments(func_get_args(), FALSE);
    if (!$requests) {
      $requests = array('drupal');
    }
  
    // Get installed projects.
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
      $projects = drush_get_projects();
    }
    else {
      $projects = array();
    }
  
    // Select the filter to apply based on cli options.
    if (drush_get_option('dev', FALSE)) {
      $filter = 'dev';
    }
    elseif (drush_get_option('all', FALSE)) {
      $filter = 'all';
    }
    else {
      $filter = '';
    }
  
    $status_url = drush_get_option('source');
  
    $output = array();
    foreach ($requests as $request) {
      $request = pm_parse_request($request, $status_url, $projects);
      $project_name = $request['name'];
      $project_release_info = $release_info->get($request);
      if ($project_release_info) {
        $version = isset($projects[$project_name]) ? $projects[$project_name]['version'] : NULL;
        $releases = $project_release_info->filterReleases($filter, $version);
        foreach ($releases as $key => $release) {
          $output["${project_name}-${key}"] = array(
            'project' => $project_name,
            'version' => $release['version'],
            'date' => gmdate('Y-M-d', $release['date']),
            'status' => implode(', ', $release['release_status']),
          ) + $release;
        }
      }
    }
    if (empty($output)) {
      return drush_log(dt('No valid projects given.'), LogLevel::OK);
    }
  
    return $output;

  }

  /**
   * Download projects from drupal.org or other sources.
   *
   * @command pm-download
   * @aliases dl
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   *
   * @param string $projects A comma delimited list of drupal.org project names, with optional version. Defaults to 'drupal'
   * @option $destination Path to which the project will be copied. If you're providing a relative path, note it is relative to the drupal root (if bootstrapped).
   * @option $use-site-dir Force to use the site specific directory. It will create the directory if it doesn't exist. If --destination is also present this option will be ignored.
   * @option $notes Show release notes after each project is downloaded.
   * @option $variant Only useful for install profiles. Possible values: 'full', 'projects', 'profile-only'.
   * @option $select Select the version to download interactively from a list of available releases.
   * @option $drupal-project-rename Alternate name for "drupal-x.y" directory when downloading Drupal project. Defaults to "drupal".
   * @option $default-major Specify the default major version of modules to download when there is no bootstrapped Drupal site.  Defaults to "8".
   * @option $skip Skip automatic downloading of libraries (c.f. devel).
   * @option $pipe Returns a list of the names of the extensions (modules and themes) contained in the downloaded projects.
   * @option $version-control=backup Backup all project files before updates.
   * @option $version-control=bzr Quickly add/remove/commit your project changes to Bazaar.
   * @option $version-control=svn Quickly add/remove/commit your project changes to Subversion.
   * @option $cache Cache release XML and tarballs or git clones. Git clones use git's --reference option. Defaults to 1 for downloads, and 0 for git.
   * @option $package-handler=wget Download project packages using wget or curl.
   * @option $package-handler=git_drupalorg Use git.drupal.org to checkout and update projects.
   * @option $source The base URL which provides project release history in XML. Defaults to http://updates.drupal.org/release-history.
   * @option $dev Work with development releases solely.
   * @usage drush dl drupal
   *   Download latest recommended release of Drupal core.
   * @usage drush dl drupal-7.x
   *   Download latest 7.x development version of Drupal core.
   * @usage drush dl drupal-6
   *   Download latest recommended release of Drupal 6.x.
   * @usage drush dl cck zen
   *   Download latest versions of CCK and Zen projects.
   * @usage drush dl og-1.3
   *   Download a specfic version of Organic groups module for my version of Drupal.
   * @usage drush dl diff-6.x-2.x
   *   Download a specific development branch of diff module for a specific Drupal version.
   * @usage drush dl views --select
   *   Show a list of recent releases of the views project, prompt for which one to download.
   * @usage drush dl webform --dev
   *   Download the latest dev release of webform.
   * @usage drush dl webform --cache
   *   Download webform. Fetch and populate the download cache as needed.
   */
  public function download($projects = '', $options = ['destination' => '', 'use-site-dir' => '', 'notes' => '', 'variant' => '', 'select' => '', 'drupal-project-rename' => '', 'default-major' => '', 'skip' => '', 'pipe' => '', 'version-control=backup' => '', 'version-control=bzr' => '', 'version-control=svn' => '', 'cache' => '', 'package-handler=wget' => '', 'package-handler=git_drupalorg' => '', 'source' => '', 'dev' => '']) {
    $release_info = drush_get_engine('release_info');
  
    if (!$requests = pm_parse_arguments(func_get_args(), FALSE)) {
      $requests = array('drupal');
    }
  
    // Pick cli options.
    $status_url = drush_get_option('source', ReleaseInfo::DEFAULT_URL);
    $restrict_to = drush_get_option('dev', '');
    $select = drush_get_option('select', 'auto');
    $all = drush_get_option('all', FALSE);
    // If we've bootstrapped a Drupal site and the user may have the chance
    // to select from a list of filtered releases, we want to pass
    // the installed project version, if any.
    $projects = array();
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
      if (!$all and in_array($select, array('auto', 'always'))) {
        $projects = drush_get_projects();
      }
    }
  
    // Get release history for each request and download the project.
    foreach ($requests as $request) {
      $request = pm_parse_request($request, $status_url, $projects);
      $version = isset($projects[$request['name']]) ? $projects[$request['name']]['version'] : NULL;
      $release = $release_info->selectReleaseBasedOnStrategy($request, $restrict_to, $select, $all, $version);
      if ($release == FALSE) {
        // Stop working on the first failure. Return silently on user abort.
        if (drush_get_context('DRUSH_USER_ABORT', FALSE)) {
          return FALSE;
        }
        // Signal that the command failed for all other problems.
        return drush_set_error('DRUSH_DOWNLOAD_FAILED', dt("Could not download requested project(s)."));
      }
      $request['version'] = $release['version'];
  
      $project_release_info = $release_info->get($request);
      $request['project_type'] = $project_release_info->getType();
  
      // Determine the name of the directory that will contain the project.
      // We face here all the assymetries to make it smooth for package handlers.
      // For Drupal core: --drupal-project-rename or drupal-x.y
      if (($request['project_type'] == 'core') ||
          (($request['project_type'] == 'profile') && (drush_get_option('variant', 'full') == 'full'))) {
        // Avoid downloading core into existing core.
        if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_ROOT) {
          if (strpos(realpath(drush_get_option('destination')), DRUPAL_ROOT) !== FALSE) {
            return drush_set_error('DRUSH_PM_DOWNLOAD_TRANSLATIONS_FORBIDDEN', dt('It\'s forbidden to download !project core into an existing core.', array('!project' => $request['name'])));
          }
        }
  
        if ($rename = drush_get_option('drupal-project-rename', FALSE)) {
          if ($rename === TRUE) {
            $request['project_dir'] = $request['name'];
          }
          else {
            $request['project_dir'] = $rename;
          }
        }
        else {
          // Set to drupal-x.y, the expected name for .tar.gz contents.
          // Explicitly needed for cvs package handler.
          $request['project_dir'] = strtolower(strtr($release['name'], ' ', '-'));
        }
      }
      // For the other project types we want the project name. Including core
      // variant for profiles.  Note those come with drupal-x.y in the .tar.gz.
      else {
        $request['project_dir'] = $request['name'];
      }
  
      // Download the project to a temporary location.
      drush_log(dt('Downloading project !name ...', array('!name' => $request['name'])));
      $request['full_project_path'] = package_handler_download_project($request, $release);
      if (!$request['full_project_path']) {
        // Delete the cached update service file since it may be invalid.
        $release_info->clearCached($request);
        drush_log(dt('Error downloading !name', array('!name' => $request['name']), LogLevel::ERROR));
        continue;
      }
  
      // Determine the install location for the project.  User provided
      // --destination has preference.
      $destination = drush_get_option('destination');
      if (!empty($destination)) {
        if (!file_exists($destination)) {
          drush_mkdir($destination);
        }
        $request['project_install_location'] = realpath($destination);
      }
      else {
        $request['project_install_location'] = _pm_download_destination($request['project_type']);
      }
  
      // If user did not provide --destination, then call the
      // download-destination-alter hook to give the chance to any commandfiles
      // to adjust the install location or abort it.
      if (empty($destination)) {
        $result = drush_command_invoke_all_ref('drush_pm_download_destination_alter', $request, $release);
        if (array_search(FALSE, $result, TRUE) !== FALSE) {
          return FALSE;
        }
      }
  
      // Load version control engine and detect if (the parent directory of) the
      // project install location is under a vcs.
      if (!$version_control = drush_pm_include_version_control($request['project_install_location'])) {
        continue;
      }
  
      $request['project_install_location'] .= '/' . $request['project_dir'];
  
      if ($version_control->engine == 'backup') {
        // Check if install location already exists.
        if (is_dir($request['project_install_location'])) {
          if (drush_confirm(dt('Install location !location already exists. Do you want to overwrite it?', array('!location' => $request['project_install_location'])))) {
            drush_delete_dir($request['project_install_location'], TRUE);
          }
          else {
            drush_log(dt("Skip installation of !project to !dest.", array('!project' => $request['name'], '!dest' => $request['project_install_location'])), LogLevel::WARNING);
            continue;
          }
        }
      }
      else {
        // Find and unlink all files but the ones in the vcs control directories.
        $skip_list = array('.', '..');
        $skip_list = array_merge($skip_list, drush_version_control_reserved_files());
        drush_scan_directory($request['project_install_location'], '/.*/', $skip_list, 'unlink', TRUE, 'filename', 0, TRUE);
      }
  
      // Copy the project to the install location.
      if (drush_op('_drush_recursive_copy', $request['full_project_path'], $request['project_install_location'])) {
        drush_log(dt("Project !project (!version) downloaded to !dest.", array('!project' => $request['name'], '!version' => $release['version'], '!dest' => $request['project_install_location'])), LogLevel::SUCCESS);
        // Adjust full_project_path to the final project location.
        $request['full_project_path'] = $request['project_install_location'];
  
        // If the version control engine is a proper vcs we also need to remove
        // orphan directories.
        if ($version_control->engine != 'backup') {
          $empty_dirs = drush_find_empty_directories($request['full_project_path'], $version_control->reserved_files());
          foreach ($empty_dirs as $empty_dir) {
            // Some VCS files are read-only on Windows (e.g., .svn/entries).
            drush_delete_dir($empty_dir, TRUE);
          }
        }
  
        // Post download actions.
        package_handler_post_download($request, $release);
        drush_command_invoke_all('drush_pm_post_download', $request, $release);
        $version_control->post_download($request);
  
        // Print release notes if --notes option is set.
        if (drush_get_option('notes') && !drush_get_context('DRUSH_PIPE')) {
          $project_release_info->getReleaseNotes($release['version'], FALSE);
        }
  
        // Inform the user about available modules a/o themes in the downloaded project.
        drush_pm_extensions_in_project($request);
      }
      else {
        // We don't `return` here in order to proceed with downloading additional projects.
        drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt("Project !project (!version) could not be downloaded to !dest.", array('!project' => $request['name'], '!version' => $release['version'], '!dest' => $request['project_install_location'])));
      }
  
  //   @todo - bring back when porting to Annotated.
      // Notify about this project.
  //    if (NotifyCommands::isAllowed('pm-download', $commandData)) {
  //      $msg = dt('Project !project (!version) downloaded to !install.', array(
  //        '!project' => $name,
  //        '!version' => $release['version'],
  //        '!install' => $request['project_install_location'],
  //      ));
  //      NotifyCommands::shutdownSend($msg), $commandData);
  //    }
    }

  }
}