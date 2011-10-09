<?php

/**
 * @file
 *   Refine your Drupal major version upgrade.
 */

/**
 * Implement hook_drush_command().
 */
function upgrade_drush_command() {
  $items = array();

  $items['site-upgrade'] = array(
    'description' => 'Run a major version upgrade for Drupal (e.g. Drupal 6 to Drupal 7).  A copy of the site is made, and then upgraded; the source site is not changed.',
    'drupal dependencies' => array('update'),
    'drush dependencies' => array('sql', 'pm', 'core'),
    'core' => array(6), // Add 7 once drush supports 7 -> 8 upgrades.
    'arguments' => array(
      'target' => "The name of a sitealias, which points to the destination site. 'root' and 'uri' keys are required; db-url is recommended. See examples/aliases.drushrc.php for more information about creating a site alias.",
    ),
    'examples' => array(
      'drush site-upgrade @onward' => 'Upgrade from the current site to the site specified by @onward alias.',
    ),
    'options' => array(
      'structure-tables-key' => 'A key in the structure-tables array. @see example.drushrc.php. Defaults to \'common\'.',
      'source-dump' => 'Path to dump file. Medium or large sized sites should set this. Optional; default is to create a temporary file.',
      'db-su' => 'DB username to use when dropping and creating the target database. Optional.',
      'db-su-pw' => 'DB password to use when dropping and creating the target database. Optional.',
      'cache' => 'Use pm-download cache to speed up re-download from drupal.org.',
      'no-cache' => 'Transfer a fresh database from source site. Otherwise, DB dump is re-used for 24 hours. Negates --cache as well.',
      'core-only' => 'Stop after upgrading Drupal core; do not download and enable new versions of the site\'s modules.',
      'force-sites-default' => 'Forces settings.php to be written in sites/default folder, even if source settings.php is not.',
      'replace' => 'Replace target if it already exists.  Default is to prompt.',
      'reuse' => 'Reuse target if it already exists.  Default is to prompt.',
      'uninstall' => 'Comma-separated list of modules to uninstall in the target database prior to upgrade.  n.b. The source site is not affected.',
    ),
    'aliases' => array('sup'),
    'topics' => array('docs-aliases'),
  );
  $items['site-upgrade-prepare'] = array(
    'description' => 'Prior to running updatedb on Drupal core, disable all contrib modules and uninstall any module the user specified should be uninstalled.  Called automatically by site-upgrade.',
    'hidden' => TRUE,
    'arguments' => array(
      'modules' => 'The modules to disable.',
    ),
    'options' => array(
      'uninstall' => 'Comma-separated list of modules to uninstall prior to upgrade.',
    ),
  );
  $items['site-upgrade-modules'] = array(
    'description' => 'Download, enable, and run updatedb on all contrib modules after an upgrade.  Called automatically by site-upgrade.',
    'hidden' => TRUE,
    'arguments' => array(
      'modules' => 'The modules to download and enable.',
    ),
    'options' => array(
      'projects' => 'The projects that must be downloaded to provide the specified module list.',
    ),
  );
  return $items;
}

/**
 * Implement hook_drush_help().
 */
function upgrade_drush_help($section) {
  switch ($section) {
    case 'drush:site-upgrade':
      return dt("Execute a major version upgrade for Drupal core and enabled contrib modules. Command will download next version of Drupal and all available contrib modules that have releases. It prepares a settings.php for the target site, and copies the prior version's database to the target site. Finally, updatedb is run. The intent is for developers to keep re-running this command until they are satisfied with the resulting site. Run this command from within your source site (D6). Note that this command uses pm-download and sql-sync internally so most options for those commands are valid here too.");
  }
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of available site aliases.
 */
function upgrade_site_upgrade_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

/**
 * Do some sanity checks to make sure that we are ready to perform an upgrade, and
 * that the command is being called with reasonable-looking parameters.
 */
function drush_upgrade_site_upgrade_check_parameters($target_key = NULL) {
  if (empty($target_key)) {
    return drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Missing argument: target'));
  }

  if (!$target_alias = drush_sitealias_get_record($target_key)) {
    return drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Site alias not found: @target-key. See example.drushrc.php.', array('@target-key' => $target_key)));
  }

  if (!file_exists(dirname($target_alias['root']))) {
    return drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Parent directory for site alias root not found: @root; this folder must exist before running site-upgrade. See example.drushrc.php.', array('@root' => dirname($target_alias['root']))));
  }

  if (realpath($target_alias['root']) == realpath(DRUPAL_ROOT)) {
    return drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Target site alias must have a different Drupal root directory than the source site.  Both are at @root.', array('@root' => $target_alias['root'])));
  }

  return TRUE;
}

/**
 * Main command hook for site-upgrade.
 *
 * This runs bootstrapped to the SOURCE site.
 */
function drush_upgrade_site_upgrade($target_key = NULL) {
  // Presume we are ready to go (n.b. some checks already performed in 'validate')
  $ready_to_upgrade = TRUE;
  $result = TRUE;

  // PREPARE:  Find the target version and determine the contrib projects and enabled modules installed.

  $source_version = drush_drupal_major_version();
  $target_version = $source_version + 1;
  if (isset($target_key)) {
    $target_alias = drush_sitealias_get_record($target_key);
    if (!empty($target_alias)) {
      $destination_core = $target_alias['root'];

      $destination_conf_path = conf_path();
      if (drush_get_option('force-sites-default')) {
        $destination_conf_path = 'sites/default';
      }
    }
  }

  // Get a list of enabled contrib extensions.
  $values = drush_invoke_process('@self', 'pm-list', array(), array('status'=>'enabled','no-core'=>TRUE), array('integrate' => FALSE, 'override-simulated' => TRUE));
  if ($values['error_status'] != 0) return FALSE;
  $contrib_extensions = array_keys($values['object']);

  // Get a list of enabled core extensions.
  $values = drush_invoke_process('@self', 'pm-list', array(), array('status'=>'enabled','core'=>TRUE), array('integrate' => FALSE, 'override-simulated' => TRUE));
  if ($values['error_status'] != 0) return FALSE;
  $core_extensions = array_keys($values['object']);

  // Make a list of modules that are not available to be enabled in the target
  // version of Drupal, either because they have not been ported yet, or because
  // they have been rolled into core.
  $unavailable_extensions = array();

  // Get the list of modules the user would like to uninstall (if any).
  $uninstall_extensions = drush_get_option('uninstall', '');
  if ($uninstall_extensions == "all") {
    $uninstall_extensions = $contrib_extensions;
  }
  else {
    $uninstall_extensions = explode(',', $uninstall_extensions);
  }

  // WARN:  Tell the user about any special situations that might exist with contrib modules.

  $project_download_list = array();
  $project_info_map = array();
  $extension_info = drush_get_extensions();
  if (!empty($contrib_extensions) && !drush_get_option('core-only')) {
    // Make a list of all of the extensions to download.  We will
    // download everything in the contrib extension list, but we
    // will skip projects that already exist.
    $special_projects = drush_upgrade_project_map($target_version);
    $in_core = array();
    $special_warning = array();
    foreach ($contrib_extensions as $extension_name) {
      // Only check extensions that are NOT in our uninstall list.
      if (!in_array($extension_name, $uninstall_extensions) && array_key_exists($extension_name, $extension_info)) {
        if (empty($extension_info[$extension_name]->info['project'])) {
          drush_log(dt('No project information could be found for the module !module. Enable git_deploy project if you are using Git clones as projects.', array('!module' => $extension_name)), 'warning');
        }
        else {
          $project = $extension_info[$extension_name]->info['project'];
          $project_info_map[$project]['name'] = $extension_info[$extension_name]->info['name'];
          $project_info_map[$project]['version'] = $extension_info[$extension_name]->info['version'];
          // Check our lookup table to see if a project has been renamed.
          if (array_key_exists($project, $special_projects['project-remap'])) {
            $project = $special_projects['project-remap'][$project];
          }
          // If the module has been rolled into core in the next major release of
          // Drupal, then we do not need to download it.  Add it to an array for
          // reporting purposes.
          if ((in_array($project, $special_projects['projects-in-core'])) || (in_array($extension_name, $special_projects['modules-in-core']))) {
            $in_core[$extension_name] = $extension_name;
            // Might some of these need to be enabled?
            $unavailable_extensions[] = $extension_name;
          }
          elseif (($extension_info[$extension_name]->type == 'module') && !is_dir($destination_core . '/sites/all/modules/' . $project) && !is_dir($destination_core . '/' . $destination_conf_path . '/modules/' . $project)) {
            $project_download_list[$project][] = $extension_name;
          }
          // If there is a special warning about a project, then add it
          // to the warning list for later reporting.
          if (array_key_exists($project . ':' . $extension_name, $special_projects['warning'])) {
            $special_warning[$project] = $special_projects['warning'][$project . ':' . $extension_name];
          }
          elseif ((array_key_exists($project, $special_projects['warning'])) && (!array_key_exists($project, $special_warning))) {
            $special_warning[$project] = $special_projects['warning'][$project];
          }
        }
      }
    }

    $rows[] = array(dt('Project'), dt('Title'), dt('Installed'), dt('Available'), dt('Status'));
    // Consider each project from the contrib extensions and check with PM to see if there is
    // a recommended release available to download.  If there is NO release available, then
    // we are not ready to upgrade (but still can, without that project); if there is no RECOMMENDED
    // release, then we might not be ready to upgrade (but still can, with a non-recommended release).
    if (!empty($project_download_list)) {
      $result = drush_invoke_process('@none', 'pm-releases', array_keys($project_download_list), array('default-major' => $target_version), array('integrate' => FALSE, 'override-simulated' => TRUE, 'log' => FALSE));
      $project_releases = $result['object'];
      foreach ($project_download_list as $project => $extension_list) {
        if (!array_key_exists($project, $project_releases) || empty($project_releases[$project]['releases'])) {
          $unavailable_extensions = array_merge($unavailable_extensions, $extension_list);
          $ready_to_upgrade = FALSE;
          $available_version = '-';
          $status = dt("NO !major_version RELEASES", array('!major_version' => $target_version . ".x"));
        }
        else {
          $all_versions = array_keys($project_releases[$project]['releases']);
          $available_version = $all_versions[0];
          if (empty($project_releases[$project]['recommended'])) {
            $ready_to_upgrade = 'maybe';
            $status = implode(',', $project_releases[$project]['releases'][$available_version]['release_status']);
          }
          else {
            $status = dt('Recommended');
          }
        }
        $title = $project_info_map[$project]['name'];
        $installed_version = $project_info_map[$project]['version'];
        $rows[] = array($project, $title, $installed_version, $available_version, $status);
      }
    }
    drush_print_table($rows);

    // Print out some messages about projects that migrated  to core, or modules that will require special processing.
    if (!empty($in_core)) {
      drush_log(dt('The following contrib modules were enabled in your Drupal site, but are now standard in core: !in_core.  These modules may need to be reconfigured after the upgrade is complete.', array('!in_core' => implode(', ', $in_core))), 'ok');
    }
    foreach ($special_warning as $project => $warning) {
      if ($warning === TRUE) {
        $warning = 'Please see !project_page and !source for more information on how to do this.';
      }
      if ($warning === FALSE) {
        $warning = 'So far there is no indication of when a migration path will be provided.  Check !project_page for updates.';
        $ready_to_upgrade = 'maybe';
      }
      drush_log(dt("You are using the project !project, which requires data migration or other special processing.  $warning", array('!project' => $project, '!project_page' => 'http://drupal.org/project/' . $project, '!source' => $special_projects['source'])), 'warning');
    }
  }

  // VALIDATE: did the user provide legal and usable parameters?

  if (drush_upgrade_site_upgrade_check_parameters($target_key) === FALSE) {
    return FALSE;
  }

  // CONFIRM:  Ask the user before overwriting an exsiting site, and ask if an upgrade is really decided if the site is not ready yet.

  // Summarize whether or not there is a good chance that the site can be upgraded.
  if ($ready_to_upgrade !== TRUE) {
    drush_log(dt("Based on the contrib modules enabled in this site, it is possible that the site-upgrade command might fail.  See warnings above."), (($ready_to_upgrade === FALSE) ? 'warning' : 'notice'));
  }
  // Check to see what we should do if the target Drupal folder already exists.
  $options = array(
    'replace' => dt("Delete the existing site and start over."),
    'reuse' => dt("Re-use the existing code, re-import the database from the source site and run updatedb again."),
  );
  $selection = NULL;
  foreach ($options as $option => $msg) {
    if (drush_get_option($option, FALSE)) {
      $selection = $option;
    }
  }
  if (!isset($selection) && (file_exists($destination_core))) {
    $selection = drush_choice($options, dt("Drupal site already exists at !root.  Would you like to:", array('!root' => $destination_core)));
    if (!$selection) {
      return drush_user_abort();
    }
  }
  elseif($ready_to_upgrade !== TRUE) {
    if (!drush_confirm('Would you like to continue anyway?')) {
      return drush_user_abort();
    }
  }

  // User has already been prompted; skip further confirms.
  drush_set_context('DRUSH_AFFIRMATIVE', TRUE);

  // We need to know where our destination settings file is regardless of which
  // code path we take; therefore, we will precompute it here.

  $settings_destination = $destination_core . '/' . $destination_conf_path . '/settings.php';

  // STEP 1:  Download the next major version of Drupal.

  if (($selection == 'replace') || (!is_dir($destination_core))) {
    drush_upgrade_download_drupal($target_version, $destination_core);
    if (drush_get_error()) return FALSE; // Early exit if we see an error.
    drush_upgrade_copy_settings($target_alias, $settings_destination);
  }
  else {
    // Move sites/all/modules and $conf_path()/modules out of the way
    // so that updatedb can be run on core only.
    if (_drush_upgrade_preserve_modules($destination_core) === FALSE) {
      return FALSE;
    }
  }

  // Copy source database to target database. The source DB is not changed.
  // Always set 'common' at minimum. Sites that want other can create other key in drushrc.php.
  if (!drush_get_option('structure-tables-key')) {
    drush_set_option('structure-tables-key', 'common');
  }
  // Empty or create the target database as needed.
  drush_sql_empty_db(drush_sitealias_get_db_spec($target_alias));
  // We still have DRUSH_AFFIRMATIVE set from above, so this will not prompt.
  drush_invoke('sql-sync', array('@self', $target_key));
  if (drush_get_error()) return FALSE; // Early exit if we see an error.

  if (!empty($contrib_extensions)) {
    $target_alias_databases = sitealias_get_databases_from_record($target_alias);
    $modify_site_conf_path = NULL;

    // Make an alias record that uses the CODE from @self and the DATABASE from $target.
    // Since we just did an sql-sync from @self to @target, we can use this hybrid specification
    // to do manipulations on the target database before runing updatedb.  In brief, we are going
    // to disable all contrib modules to prevent problems with updatedb.
    $modify_site = array (
      'root' => DRUPAL_ROOT,
      'uri' => $target_alias_databases['default']['default']['database'],
    );

    if (!drush_get_context('DRUSH_SIMULATE')) {
      // In theory, if the sql-sync worked, this should never be empty.
      if (empty($modify_site['uri'])) {
        return drush_set_error('DRUSH_UPGRADE_DATABASE_SPEC_UNKNOWN', dt('Failed to look up database spec for @target', array('@target' => $target_key)));
      }
      $modify_site_conf_path = dirname(conf_path()) . '/' . $modify_site['uri'];
      $modify_site_settings = $modify_site_conf_path . '/settings.php';
      drush_log('Set up a transient site by copying ' . $settings_destination . ' to ' . $modify_site_settings, 'debug');
      if (!file_exists($modify_site_settings)) {
        if ((drush_mkdir($modify_site_conf_path) === FALSE) || drush_op('copy', $settings_destination, $modify_site_settings) !== TRUE) {
          return drush_set_error('DRUSH_UPGRADE_COULD_NOT_DISABLE', dt("Could not create a temporary multisite "));
        }
      }
    }
    $result = (drush_invoke_process($modify_site, 'site-upgrade-prepare', $contrib_extensions, array('uninstall' => implode(',', $uninstall_extensions), 'yes' => TRUE), array('interactive' => TRUE)) == 0);

    // Delete the temporary site now that we're done with it.
    if (isset($modify_site_conf_path)) {
      drush_delete_dir($modify_site_conf_path);
    }
    if ($result === FALSE) return FALSE;
  }

  // STEP 2:  Call updatedb for Drupal core.

  // Run update.php in a subshell. It is run on @target site whereas this request was on @self.
  drush_log(dt('About to perform updatedb for Drupal core on !target', array('!target' => $target_key)), 'ok');
  // When we call drush_invoke_process in #interactive mode, the result code comes from drush_op_system, where 0 == success.
  $result = drush_invoke_process($target_alias, 'updatedb', array(), array('yes' => TRUE), array('interactive' => TRUE)) == 0;
  if ($result === FALSE) {
    return drush_set_error('DRUSH_DRUPAL_CORE_UPGRADE_FAILED', dt("The call to updatedb failed for Drupal core.  This may be caused by a contrib module that is not yet ready for upgrade.  Try running site-upgrade again with '--uninstall={module list}' to remove all contrib extensions prior to upgrade.  Add modules back in until the problematic one is isolated.  Please report problems in the issue queue of the module that is causing problems."));
  }
  drush_log(dt('updatedb complete for Drupal core'), 'ok');

  // If we moved our modules out of the way, bring them back now.
  _drush_upgrade_restore_preserved_modules();

  // STEP 3: Download and re-enable the contrib modules.

  if (!empty($contrib_extensions) && !drush_get_option('core-only')) {
    $options = array();
    $backend_options = array('interactive' => TRUE);
    if (!empty($project_download_list)) {
      $projects = implode(',', array_keys($project_download_list));
      $options['projects'] = $projects;
    }
    // If a module changed name, then rename it prior to calling pm-enable.
    foreach ($contrib_extensions as $extension_name) {
      if (array_key_exists($extension_name, $special_projects['module-remap'])) {
        $unavailable_extensions[] = $extension_name;
        $contrib_extensions[] = $special_projects['module-remap'][$extension_name];
      }
    }

    // Redispatch to site-upgrade-modules command, so that we will be
    // bootstrapped to the target site.
    $result = (drush_invoke_process($target_alias, 'site-upgrade-modules', array_merge($core_extensions, array_diff($contrib_extensions, $unavailable_extensions, $uninstall_extensions)), $options, $backend_options) == 0);
  }

  return $result;
}

/**
 * http://drupal.org/node/895314 lists projects that are now in
 * core; it also has a list of projects that require special handling.
 * Keep a record here too.
 *
 * @param $target_version
 * The version of Drupal being upgraded to.
 * @return @array
 *   - source              URL to the page where more information about this upgrade can be found.
 *   - target-version      The version of Drupal being upgraded to.
 *   - projects-in-core    An array containing projects that were once contrib that are now in core.
 *   - modules-in-core     An array containing modules that were once in contrib that are now in core.
 *                         Use 'modules-in-core' in place of 'projects-in-core' only if there is a
 *                         project where only some of its subcomponents were moved to core.
 *   - warning             An array of warning messages to display to the user related to this upgrade.
 *                         The key should be the name of the project that the warning applies to.
 *                         use 'project:module' as the key if the warning only applies when the given
 *                         module in the project is enabled; otherwise, the warning will be shown whenever
 *                         any module in the specific project is enabled.  The value can either be the
 *                         warning string to display, or TRUE to tell the user to see the project page
 *                         for information on the migration path, or FALSE to tell the user that there
 *                         currently is no migration path for the given project.
 */
function drush_upgrade_project_map($target_version) {
  $result = array(
    'source' => '',
    'target-version' => $target_version,
    'projects-in-core' => array(),
    'modules-in-core' => array(),
    'warning' => array(),
  );
  if ($target_version == 7) {
    $result = array(
      'source' => 'http://drupal.org/node/895314',
      'projects-in-core' => array(
        'abssrc',
        'admin_hover',
        'adminrole',
        'ahah_helper',
        'autoload',
        'automaticmenu',
        'automenu',
        'auto_menutitle',
        'block_edit',
        'block_node_visibility',
        'blocks404',
        'canonical_url',
        'checkbox_validate',
        'comment_display',
        'config_perms',
        'ctm',
        'dbtng',
        'documentation',
        'drupal_queue',
        'edit_term',
        'elements',
        'element_themehook',
        'filefield',
        'filter_default',
        'hook_file',
        'imageapi',
        'imagecache',
        'imagefield',
        'input_format_permissions',
        'jq',
        'jqp',
        'jquery_cookie',
        'jquery_form_update',
        'jquery_ui',
        'jsalter',
        'login_security',
        'menuless_nodetype',
        'nodepreview_by_type',
        'parallel',
        'permissions_api',
        'phpass',
        'plugin_manager',
        'plugins',
        'poormanscron',
        'preview',
        'rdf',
        'shortlink',
        'simplecdn',
        'simpletest',
        'storage_api',
        'tar',
        'taxidermy',
        'tinymce_ahah',
        'tinymce_dragdrop',
        'transaction',
        'upload_element',
        'upload_preview',
        'url_alter',
        'user_cancellation',
        'user_default_filter',
        'user_delete',
        'vertical_tabs',
        'view_unpublished',
        'vocabperms',
        'wysiwygcck',
      ),
      'modules-in-core' => array(
        // 'date' project is still contrib, but date_timezone, one of its modules, has moved to core. See http://drupal.org/node/868028.
        'date_timezone',
      ),
      'project-remap' => array(),
      'module-remap' => array(
        'content' => 'cck',
      ),
      'warning' => array(
        'token' => dt('In Drupal 7, the contrib token module handles UI, as well as field and profile tokens; all other functionality has been migrated to core.'),
        'cck' => TRUE,
        'content_taxonomy' => dt('The project page for this module (!project_page) says that an upgrade path will be provided for this module at a future time.'),
        'date:date_api' => dt("The d6 version of the date_api module in the date project defines a table called date_formats, which is also defined by system/system.install schema in d7.  If this problem has not been fixed yet, then the updatedb function will fail, and it will not be possible to upgrade to d7.  If this happens, disable and uninstall the date_api module before running site-upgrade (i.e. add '--uninstall=date_api' to site-upgrade call).  See http://drupal.org/node/1013034."),
        'field_taxonomy' => FALSE,
        'filefield' => dt('Data migration for this module will be provided by the Content Migrate submodule of cck.  Enable content_migrate after upgrading; see http://drupal.org/node/781088.'),
        'imagefield' => dt('Data migration for this module will be provided by the Content Migrate submodule of cck.  Enable content_migrate after upgrading; see http://drupal.org/node/781088.'),
        'taxonomy_delegate' => FALSE,
        'taxonomy_intro' => FALSE,
        'term_fields' => FALSE,
      ),
    );
  }
  drush_command_invoke_all_ref('drush_upgrade_project_map_alter', $result);

  return $result;
}

/**
 * Prepare to upgrade; the first step is to disable all contrib modules.
 */
function drush_upgrade_site_upgrade_prepare() {
  $contrib_extensions = func_get_args();
  $uninstall_extensions = explode(',', drush_get_option('uninstall', ''));

  // Set theme back to garland per Upgrade.txt.
  variable_set('theme_default', 'garland');

  // http://drupal.org/node/724102 recommends using "seven" as your admin theme.  Don't switch it to garland if it is already seven.
  $admin_theme = variable_get('admin_theme', NULL);
  if ($admin_theme != "seven") {
    variable_set('admin_theme', 'garland');
  }
  else {
    drush_log(dt("Admin theme is already set to 'seven'."), 'ok');
  }

  // Disable all contrib modules per Upgrade.txt.
  drush_log(dt("Disabling !list", array('!list' => implode(", ", $contrib_extensions))), 'ok');
  drush_invoke('pm-disable', $contrib_extensions);
  if (drush_get_error()) return FALSE; // Early exit if we see an error.

  // Uninstall any modules specified via the --uninstall flag.
  if (!empty($uninstall_extensions)) {
    drush_log(dt("Uninstalling !list", array('!list' => implode(", ", $uninstall_extensions))), 'ok');
    drush_invoke('pm-uninstall', $uninstall_extensions);
    if (drush_get_error()) return FALSE; // Early exit if we see an error.
  }
}

/**
 * Upgrade all of the contrib modules of the site being upgraded.
 *
 * This runs bootstrapped to the TARGET site, after the new version
 * of Drupal has been downloaded, and after updatedb has been run
 * for Drupal core.
 */
function drush_upgrade_site_upgrade_modules() {
  $extensions_to_enable = func_get_args();
  $project_download_list = drush_get_option('projects', '');

  if (!empty($project_download_list)) {
    // Download our contrib extensions.
    drush_log(dt('Download projects: !projects', array('!projects' => $project_download_list)), 'ok');
    drush_set_option('destination', NULL);
    // User has already been prompted; if there is no recommended release,
    // we will just take the most recent.
    drush_set_option('choice', '1');
    drush_invoke('pm-download', $project_download_list);
  }

  // Run updatedb to update all of the contrib extensions.
  drush_log(dt('About to perform updatedb for extensions'), 'ok');
  $result = drush_invoke_process('@self', 'updatedb', array(), array('yes' => TRUE), array('interactive' => TRUE));
  if ($result === FALSE) {
    return drush_set_error('DRUSH_DRUPAL_CONTRIB_UPGRADE_FAILED', dt("The call to updatedb failed for the enabled contrib modules.  Try running site-upgrade again with '--uninstall={module list}' to remove all contrib extensions prior to upgrade.  Add modules back in until the problematic one is isolated.  Please report problems in the issue queue of the module that is causing problems."));
  }
  drush_log(dt('updatedb complete for extensions'), 'ok');

  // Finally, enable the modules that site-upgrade previously disabled.
  // We will set the option --resolve-dependencies to pick up new modules
  // that may now be required; for example, views-7.x picked up a dependency
  // on ctools that views-6.x did not have.  We also set DRUSH_AFFIRMATIVE,
  // so everything from here on out will be processed with --yes.
  drush_set_option('resolve-dependencies', TRUE);
  drush_set_context('DRUSH_AFFIRMATIVE', TRUE);
  $result = drush_invoke('pm-enable', $extensions_to_enable);

  return $result;
}

/**
 * Download the upgraded version of Drupal for site-upgrade.
 */
function drush_upgrade_download_drupal($target_version, $destination_core) {
  // Fetch target core and place as per target alias root.
  drush_set_option('destination', dirname($destination_core));
  drush_set_option('drupal-project-rename', basename($destination_core));

  // No need for version control in this command.
  drush_set_option('version-control', 'backup');

  if (drush_get_context('DRUSH_SIMULATE')) {
    drush_log(dt("Simulated download of drupal-!version", array('!version' => $target_version)));
  }
  else {
    drush_invoke('pm-download', 'drupal-' . $target_version);
    if (drush_get_error()) return FALSE; // Early exit if we see an error.

    // Check and see if there is a Drupal site at the target.
    if (!file_exists($destination_core . '/includes/bootstrap.inc')) {
      return drush_set_error('DRUSH_UPGRADE_NO_DRUPAL', dt('Drupal could not be downloaded to the target directory, @root.  Move existing content out of the way first.', array('@root' => $target_alias['root'])));
    }
  }
}

/**
 * Copy the settings.php file from the source site to the target site,
 * and fix it up so that it will have its own database settings.
 */
function drush_upgrade_copy_settings(&$target_alias, $settings_destination) {
  $settings_destination_folder = dirname($settings_destination);

  // Create sites subdirectory in target if needed.
  $settings_source = conf_path() . '/settings.php';
  if (!file_exists($settings_destination_folder)) {
    if (!drush_op('mkdir', $settings_destination_folder) && !drush_get_context('DRUSH_SIMULATE')) {
      return drush_set_error('DRUSH_UPGRADE_MKDIR_FAILED', dt('Failed to create directory @settings_destination', array('@settings_destination' => $settings_destination_folder)));
    }
  }

  // Copy settings.php to target.
  if (!file_exists($settings_destination)) {
    if (!drush_op('copy', $settings_source, $settings_destination) && !drush_get_context('DRUSH_SIMULATE')) {
      return drush_set_error('DRUSH_UPGRADE_COPY_FAILED', dt('Failed to copy @source to  @dest', array('@source' => $settings_source, 'dest' => $settings_destination)));
    }
  }
  // Append new $db_url with new DB name in target's settings.php.
  return drush_upgrade_fix_db_url($target_alias, $settings_destination);
}

/**
 * Replace db_url with DB name from target. updatedb will later append a DBTNG compatible version.
 */
function drush_upgrade_fix_db_url(&$target_alias, $settings_destination) {
  $old_url = $GLOBALS['db_url'];
  if (is_array($old_url)) {
    $old_url = $old_url['default'];
  }
  $old_databases = empty($GLOBALS['databases']) ? drush_sitealias_convert_db_from_db_url($old_url) : $GLOBALS['databases'];

  $target_alias_databases = sitealias_get_databases_from_record($target_alias);
  $database_name = $target_alias_databases['default']['default']['database'];
  if (empty($database_name)) {
    $database_name = str_replace("@", "", $target_alias['name']) . "db";
    drush_log(dt("No database name specified; defaulting to !dbname", array("!dbname" => $database_name)), 'notice');
  }

  $append = "\n# Added by drush site-upgrade.";
  if (drush_drupal_major_version() <= 6) {
    $new_url = substr($old_url, 0, strrpos(trim($old_url), '/')) . '/'. $database_name;
    $append .= "\n" . '$db_url = \'' . $new_url . '\';';
    $databases = drush_sitealias_convert_db_from_db_url($new_url);
  }
  else {
    $databases = $GLOBALS['databases'];
    $databases['default']['default']['database'] = $target_alias_databases['default']['default']['database'];
    $append .= "\n" . '$databases = ' . var_export($databases, TRUE) . ';';
  }
  // Caching the database record in the alias record allows sql-sync to work
  // before updatedb is called. updatedb is what converts from a db_url to a
  // DBTNG array; this conversion is required by sql-sync.
  drush_sitealias_cache_db_settings($target_alias, $databases);

  // Also append the new configuration options to the end of settings.php.
  drush_op('file_put_contents', $settings_destination, $append, FILE_APPEND);
}

/**
 * Rollback function: restore our modules if updatedb fails.
 */
function drush_upgrade_site_upgrade_rollback($target_key) {
  _drush_upgrade_restore_preserved_modules();
}

/**
 * Preserve existing modules.  Move them out of the way prior
 * to updatedb of Drupal core.  We will move them back afterwards.
 */
function _drush_upgrade_preserve_modules($destination_core) {
  $modules_preserve['root'] = $destination_core;
  $modules_preserve['list'] = array();

  $moduledir_list = array(
    'sites_all_modules' => $destination_core . '/sites/all/modules',
    'sites_conf_path_modules' => $destination_core . conf_path() . '/modules',
  );

  foreach ($moduledir_list as $moduledir_name => $moduledir) {
    if (is_dir($moduledir)) {
      $preserved_moduledir = drush_tempnam($moduledir_name, dirname($moduledir));
      $result = drush_move_dir($moduledir, $preserved_moduledir, TRUE);
      if ($result) {
        $modules_preserve['list'][$moduledir] = $preserved_moduledir;
        drush_log(dt('Move !src to !dest prior to updatedb on Drupal core.', array('!src' => $moduledir, '!dest' => $preserved_moduledir)), 'ok');
      }
      else {
        return drush_set_error('DRUSH_MODULE_PRESERVE_FAILED', dt('Failed to move !src to !dest.', array('!src' => $moduledir, '!dest' => $preserved_moduledir)));
      }
    }
  }

  drush_set_context('DRUSH_MODULES_PRESERVE', $modules_preserve);
  return TRUE;
}

/**
 * Restore modules that were preserved by _drush_upgrade_preserve_modules.
 */
function _drush_upgrade_restore_preserved_modules() {
  $modules_preserve = drush_get_context('DRUSH_MODULES_PRESERVE', array());

  if (!empty($modules_preserve) && array_key_exists('list', $modules_preserve)) {
    foreach ($modules_preserve['list'] as $moduledir => $preserved_moduledir) {
      drush_move_dir($preserved_moduledir, $moduledir, TRUE);
    }
  }
  drush_set_context('DRUSH_MODULES_PRESERVE', array());
}
