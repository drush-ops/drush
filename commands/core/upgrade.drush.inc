<?php
// $Id$

/**
 * @file
 *   Refine your Drupal major version upgrade.
 *
 * @todo Upgrade to specific releases.
 */

/**
 * Implement hook_drush_command().
 */
function upgrade_drush_command() {
  $items = array();

  $items['site-upgrade'] = array(
    'description' => "Run a major version upgrade for Drupal (e.g. Drupal 6 to Drupal 7).",
    'drupal dependencies' => array('update'),
    'drush dependencies' => array('sql', 'pm', 'core'),
    'core' => array(6), // Add 7 once drush supports 7 -> 8 upgrades.
    'arguments' => array(
      'target' => 'The name of a sitealias, which points to the destination site. root and uri keys are required; db-url is recommended. See examples/aliases.drushrc.php for more information about creating a site alias.'),
    'examples' => array(
      'drush site-upgrade @onward' => 'Upgrade from the current site to the site specified by @onward alias.'
    ),
    'options' => array(
      'structure-tables-key' => 'A key in the structure-tables array. @see example.drushrc.php. Defaults to \'common\'.',
      'source-dump' => 'Path to dump file. Medium or large sized sites should set this. Optional; default is to create a temporary file.',
      'db-su' => 'DB username to use when dropping and creating the target database. Optional.',
      'db-su-pw' => 'DB password to use when dropping and creating the target database. Optional.',
      'no-cache' => 'Transfer a fresh database from source site. Otherwise, DB dump is re-used for 24 hours.',
      'core-only' => 'Stop after upgrading Drupal core; do not download and enable new versions of the site\'s modules.',
      'force-sites-default' => 'Forces settings.php to be written in sites/default folder, even if source settings.php is not.',
    ),
    'aliases' => array('sup'),
    'topics' => array('docs-aliases'),
  );
  $items['site-upgrade-modules'] = array(
    'description' => dt('Download, enable, and run updatedb on all non-core modules after an upgrade.  Called automatically by site-upgrade.'),
    'hidden' => TRUE,
    'arguments' => array(
      'modules' => 'The modules to download and enable.',
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
 * Do some sanity checks to make sure that we are ready to perform an upgrade, and
 * that the command is being called with reasonable-looking parameters.
 */
function drush_upgrade_site_upgrade_validate($target_key = NULL) {
  if (empty($target_key)) {
    return drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Missing argument: target'));
  }

  if (!$target_alias = drush_sitealias_get_record($target_key)) {
    return drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Site alias not found: @target-key. See example.drushrc.php.', array('@target-key' => $target_key)));
  }

  if (!file_exists(dirname($target_alias['root']))) {
    drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Parent directory for site alias root not found: @root; this folder must exist before running site-upgrade. See example.drushrc.php.', array('@root' => dirname($target_alias['root']))));
  }

  if (realpath($target_alias['root']) == realpath(DRUPAL_ROOT)) {
    drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt('Target site alias must have a different Drupal root directory than the source site.  Both are at @root.', array('@root' => $target_alias['root'])));
  }
}

/**
 * Main command hook for site-upgrade.
 *
 * This runs bootstrapped to the SOURCE site.
 */
function drush_upgrade_site_upgrade($target_key) {

  // PREPARE:  Find the target version and determine the non-core projects and enabled modules installed

  $source_version = drush_drupal_major_version();
  $target_version = $source_version + 1;
  $target_alias = drush_sitealias_get_record($target_key);
  if (empty($target_alias)) {
    return drush_set_error('DRUSH_UPGRADE_NO_TARGET', dt("Could not find target site for upgrade: !target", array("!target" => $target_key)));
  }

  $destination_core = $target_alias['root'];

  // Get a list of enabled non-core extensions
  $result = drush_invoke_process_args('pm-list', array(), array('status'=>'enabled','no-core'=>TRUE, '#integrate' => FALSE));
  $non_core_extensions = array_keys($result['object']);

  // CONFIRM:  Ask the user before overwriting an exsiting site

  // Check to see what we should do if the target Drupal folder already exists
  $selection = 'replace';
  if (file_exists($destination_core)) {
    $options = array(
      'replace' => dt("Delete the existing site and start over"),
      'reuse' => dt("Re-use the existing site, skipping the Drupal download and updatedb steps"),
    );
    $selection = drush_choice($options, dt("Drupal site already exists at !root.  Would you like to:", array('!root' => $destination_core)));
    if (!$selection) {
      return drush_user_abort();
    }
  }

  // STEP 1:  Download the next major version of Drupal

  if ($selection == 'replace') {
    // Fetch target core and place as per target alias root.
    drush_set_option('destination', dirname($destination_core));
    drush_set_option('drupal-project-rename', basename($destination_core));

    // No need for version control in this command.
    drush_set_option('version-control', 'backup');

    // TODO: get releases other than dev snapshot.
    drush_pm_download('drupal-'. $target_version . '.x');
    if (drush_get_error()) return FALSE; // Early exit if we see an error.

    // Check and see if there is a Drupal site at the target
    if (!file_exists($destination_core . '/includes/bootstrap.inc')) {
      return drush_set_error('DRUSH_UPGRADE_NO_DRUPAL', dt('Drupal could not be downloaded to the target directory, @root.  Move existing content out of the way first.', array('@root' => $target_alias['root'])));
    }

    // Create sites subdirectory in target if needed.
    $settings_source = conf_path() . '/settings.php';
    $settings_destination = $destination_core . '/' . $settings_source;
    if (drush_get_option('force-sites-default')) {
      $settings_destination = $destination_core . '/sites/default/settings.php';
    }
    $settings_destination_folder = dirname($settings_destination);
    if (!file_exists($settings_destination_folder)) {
      if (!drush_op('mkdir', $settings_destination_folder) && !drush_get_context('DRUSH_SIMULATE')) {
        return drush_set_error(dt('Failed to create directory @settings_destination', array('@settings_destination' => $settings_destination_folder)));
      }
    }

    // Copy settings.php to target.
    if (!file_exists($settings_destination)) {
      if (!drush_op('copy', $settings_source, $settings_destination) && !drush_get_context('DRUSH_SIMULATE')) {
        return drush_set_error(dt('Failed to copy @source to  @dest', array('@source' => $settings_source, 'dest' => $settings_destination)));
      }
    }

    // Append new $db_url with new DB name in target's settings.php.
    drush_upgrade_fix_db_url($target_alias, $settings_destination);

    // Copy source database to target database. The source DB is not changed.
    // Always set 'common' at minimum. Sites that want other can create other key in drushrc.php.
    if (!drush_get_option('structure-tables-key')) {
      drush_set_option('structure-tables-key', 'common');
    }
    // Always blow away the target database so we start fresh.
    drush_set_option('create-db', TRUE);
    drush_include(DRUSH_BASE_PATH . '/commands/sql', 'sync.sql');
    drush_invoke('sql-sync', '@self', $target_key);
    if (drush_get_error()) return FALSE; // Early exit if we see an error.

    if (!empty($non_core_extensions)) {
      // Make an alias record that uses the CODE from @self and the DATABASE from $target.
      // Since we just did an sql-sync from @self to @target, we can use this hybrid specification
      // to do manipulations on the target database before runing updatedb.  In brief, we are going
      // to disable all non-core modules to prevent problems with updatedb.
      $modify_site = array (
        'root' => DRUPAL_ROOT,
        'uri' => $target_alias['databases']['default']['default']['database'],
      );
      $modify_site_conf_path = dirname(conf_path()) . '/' . $modify_site['uri'];
      $modify_site_settings = $modify_site_conf_path . '/settings.php';
      if ((drush_mkdir($modify_site_conf_path) === FALSE) || drush_op('copy', $settings_destination, $modify_site_settings) !== TRUE) {
        return drush_set_error('DRUSH_UPGRADE_COULD_NOT_DISABLE', dt("Could not create a temporary multisite "));
      }

      // set theme back to garland per Upgrade.txt
      $result = drush_invoke_sitealias_args($modify_site, 'variable-set', array('theme_default', 'garland'), array('always-set' => TRUE, '#integrate' => TRUE));
      // http://drupal.org/node/724102 recommends using "seven" as your admin theme.  Don't switch it to garland if it is already seven.
      $admin_theme = variable_get('admin_theme', NULL);
      if ($admin_theme != "seven") {
        $result = drush_invoke_sitealias_args($modify_site, 'variable-set', array('admin_theme', 'garland'), array('always-set' => TRUE, '#integrate' => TRUE));
      }
      else {
        drush_log(dt("Admin theme is already set to 'seven'."), 'ok');
      }

      // disable all non-core modules per Upgrade.txt
      drush_log(dt("Disable non-core extensions !list", array('!list' => implode(",", $non_core_extensions))), 'ok');
      $result = drush_invoke_sitealias_args($modify_site, 'pm-disable', $non_core_extensions, array('#integrate' => TRUE));

      // TODO: http://drupal.org/node/895314 lists projects that
      // do not exist in Drupal 7 because they are already part of core.
      // We could hard-code a database of projects to skip (keyed by $target_version)
      // and remove any module in $non_core_extensions that is part of one
      // of these modules.
    }

    // STEP 2:  Call updatedb for Drupal core

    // Run update.php in a subshell. It is run on @target site whereas this request was on @self.
    drush_log(dt('About to perform updatedb for Drupal core on !target', array('!target' => $target_key)), 'ok');
    $result = drush_do_site_command($target_alias, 'updatedb', array(), array('yes' => TRUE, '#interactive' => TRUE), TRUE);
    drush_log(dt('updatedb complete for Drupal core'), 'ok');
  }

  // STEP 3: Download and re-enable the non-core modules

  if (!empty($non_core_extensions) && !drush_get_option('core-only')) {
    // Redispatch to site-upgrade-modules command, so that we will be
    // bootstrapped to the target site.
    $result = drush_invoke_sitealias_args($target_alias, 'site-upgrade-modules', $non_core_extensions, array('#interactive' => TRUE));
  }
}

/**
 * Upgrade all of the non-core modules of the site being upgraded.
 *
 * This runs bootstrapped to the TARGET site, after the new version
 * of Drupal has been downloaded, and after updatedb has been run
 * for Drupal core.
 */
function drush_upgrade_site_upgrade_modules() {
  $non_core_extensions = func_get_args();

  // Download our non-core extensions.  We do not force --yes here so that
  // pm-download can prompt for the version to download if there is no
  // recommended release available for the upgrade.
  drush_log(dt('Download modules: !modules', array('!modules' => implode(' ', $non_core_extensions))), 'ok');
  drush_set_option('destination', NULL);
  call_user_func_array('drush_pm_download', $non_core_extensions);

  // Run updatedb to update all of the non-core extensions
  drush_log(dt('About to perform updatedb for extensions'), 'ok');
  $result = drush_invoke_process_args('updatedb', array(), array('yes' => TRUE, '#interactive' => TRUE));
  drush_log(dt('updatedb complete for extensions'), 'ok');

  // Finally, enable the modules that site-upgrade previously disabled.
  // We will set the option --resolve-dependencies to pick up new modules
  // that may now be required; for example, views-7.x picked up a dependency
  // on ctools that views-6.x did not have.  We also set DRUSH_AFFIRMATIVE,
  // so everything from here on out will be processed with --yes.
  drush_set_option('resolve-dependencies', TRUE);
  drush_set_context('DRUSH_AFFIRMATIVE', TRUE);
  drush_invoke_args('pm-enable', $non_core_extensions);
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

  // Also append the new configuration options to the end of settings.php
  drush_op('file_put_contents', $settings_destination, $append, FILE_APPEND);
}
