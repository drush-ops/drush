<?php
// $Id$

// Perform setup tasks for installation.
function drush_core_pre_site_install() {

  if (!$db_spec = drush_core_site_install_db_spec()) {
    drush_set_error(dt('Could not determine database connection parameters. Pass --db-url option.'));
    return;
  }

  
  if ($sites_subdir = drush_get_option('sites-subdir')) {
    // Needed so that we later bootstrap into the right site.
    drush_set_option('uri', $sites_subdir);
  }
 else {
    $sites_subdir = 'default';
  }

  $conf_path = "sites/$sites_subdir";
  $files = "$conf_path/files";
  $settingsfile = "$conf_path/settings.php";
  if (!file_exists($files)) {
    $msg[] = dt('create a @files directory', array('@files' => $files));
  }
  if (!file_exists($settingsfile)) {
    $msg[] = dt('create a @settingsfile file', array('@settingsfile' => $settingsfile));
  }
  $msg[] = dt("DROP your '@db' database and then CREATE a new one.", array('@db' => $db_spec['database']));

  if (!drush_confirm(dt('You are about to ') . implode(dt(' and '), $msg) . ' Do you want to continue?')) {
    return drush_set_error('CORE_SITE_INSTALL_ABORT', 'Aborting.');
  }

  // Can't install without sites directory and settings.php.
  if (!file_exists($conf_path)) {
    if (!drush_op('mkdir', $conf_path) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to create directory @conf_path', array('@conf_path' => $conf_path)));
      return;
    }
  }
  else {
    drush_log(dt('Sites directory @subdir already exists - proceeding.', array('@subdir' => $conf_path)));
  }
  if (!file_exists($settingsfile)) {
    if (!drush_op('copy', 'sites/default/default.settings.php', $settingsfile) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to copy sites/default/default.settings.php to  @settingsfile', array('@settingsfile' => $settingsfile)));
      return;
    }
    elseif (drush_drupal_major_version() == 6) {
      // On D6, we have to write $db_url ourselves. On D7+, the installer does it.
      file_put_contents($settingsfile, "\n" . '$db_url = \'' . drush_get_option('db-url') . "';\n", FILE_APPEND);
    }
  }

  // Add a files dir if needed
  if (!file_exists($files)) {
    if (!drush_op('mkdir', $files) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to create directory @name', array('@name' => $files)));
      return;
    }
  }

  // Now we can bootstrap up to the specified site if not already there.
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);

  // Drop and create DB if needed.
  // @TODO: support db-su like sql sync.

  // Can't use drush_sql_query() since might not have a DB.
  // Get credentials to connect to the server, but not the database which we
  // are about to DROP.

  // Save the database name before we unset() it.
  $db_name = $db_spec['database'];
  $scheme = _drush_sql_get_scheme($db_spec);
  $simulate = drush_get_context('DRUSH_SIMULATE');

  if ($scheme === 'sqlite') {
    // With SQLite, we don't DROP DATABASEs. Each database is in a single file,
    // so we just remove the file. We also don't CREATE DATABASEs; it is created
    // when SQLite attempts to open a database file which doesn't exist.
    if (file_exists($db_spec['database']) && !$simulate) {
      if (!unlink($db_spec['database'])) {
        drush_set_error(dt('Could not drop database: @name', array('@name' => $db_name)));
      }
    }
    // Nothing else to do for SQLite installations, so return.
    return;
  }
  // _drush_sql_get_credentials() will set a default database according to
  // the scheme if one is not set.
  unset($db_spec['database']);
  $credentials = _drush_sql_get_credentials($db_spec);

  $db_quoted_name = "\"$db_name\"";
  $scheme_command = $scheme;
  switch ($scheme) {
    case 'mysql':
      $command_parameter_name = 'execute';
      $db_quoted_name = "`$db_name`";
      break;
    case 'pgsql':
      $scheme_command = 'psql --dbname=template1';
      $command_parameter_name = 'command';
      break;
  }
  $execute = "$scheme_command $credentials ";
  
  // Save $query to a tmp file. We will redirect it in. Fewer shell scaping issues that way.
  $query = "DROP DATABASE IF EXISTS $db_quoted_name";
  if ($filename = drush_save_data_to_temp_file($query)) {
    // In --simulate mode, drush_op will show the call to mysql or psql,
    // but the sql query itself is stored in a temp file and not displayed.
    // We will therefore show the query explicitly in the interest of full disclosure.
    if (drush_get_context('DRUSH_SIMULATE')) {
      drush_print('sql-query: ' . $query);
    }
    if (drush_op_system("$execute < $filename")) {
      drush_set_error(dt('Could not drop database: @name', array('@name' => $db_name)));
      return;  
    }
  }
  
  $query = "CREATE DATABASE $db_quoted_name";
  if ($filename = drush_save_data_to_temp_file($query)) {
    // In --simulate mode, drush_op will show the call to mysql or psql,
    // but the sql query itself is stored in a temp file and not displayed.
    // We will therefore show the query explicitly in the interest of full disclosure.
    if (drush_get_context('DRUSH_SIMULATE')) {
      drush_print('sql-query: ' . $query);
    }
    if (drush_op_system("$execute < $filename")) {
      drush_set_error(dt('Could not drop database: @name', array('@name' => $db_name)));  
    }
    return;
  }
}

// Command callback.
function drush_core_site_install($profile = NULL) {
  drush_include_engine('drupal', 'site_install', drush_drupal_major_version());
  drush_core_site_install_version($profile);
}

// Return a db_spec based on supplied db_url/db_prefix options or
// an existing settings.php.
function drush_core_site_install_db_spec() {
  if ($db_url = drush_get_option('db-url')) {
    // We were passed a db_url. Usually a fresh site.
    $db_spec = drush_convert_db_from_db_url($db_url);
    $db_spec['db_prefix'] = drush_get_option('db-prefix');
    return $db_spec;
  }
  elseif (drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
    // We have an existing settings.php.
    $db_spec = _drush_sql_get_db_spec();
    $db_spec['db_prefix'] = $GLOBALS['db_prefix'];
    return $db_spec;
  }
  else {
    return FALSE;
  }
}
