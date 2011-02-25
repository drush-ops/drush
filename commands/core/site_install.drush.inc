<?php

/**
 * Command validate.
 */
function drush_core_site_install_validate() {
  if ($sites_subdir = drush_get_option('sites-subdir')) {
    $lower = strtolower($sites_subdir);
    if ($sites_subdir != $lower) {
      drush_log(dt('Only lowercase sites-subdir are valid. Switching to !lower.', array('!lower' => $lower)), 'warning');
      drush_set_option('sites-subdir', $lower);
    }
  }
}

/**
 * Perform setup tasks for installation.
 */
function drush_core_pre_site_install() {
  if (!$db_spec = drush_core_site_install_db_spec()) {
    drush_set_error(dt('Could not determine database connection parameters. Pass --db-url option.'));
    return;
  }
  if ($sites_subdir = drush_get_option('sites-subdir')) {
    // Needed so that we later bootstrap into the right site.
    drush_set_option('uri', 'http://'.$sites_subdir);
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
    return drush_user_abort();
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

  // Now we can bootstrap up to the specified site.
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);

  // Drop and create DB if needed.
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
  }
  else {
    $sql = drush_sql_build_createdb_sql($db_spec, TRUE);
    // Get credentials to connect to the server, but not the database which we
    // are about to DROP. @see _drush_sql_get_credentials().
    $create_db_spec = $db_spec;
    unset($create_db_spec['database']);
    if (!_drush_sql_query($sql, $create_db_spec)) {
      // If we could not drop the database, try instead to drop all
      // of the tables in the database (presuming it exists...).
      // If we cannot do either operation, then fail with an error.
      if (!_drush_sql_drop($db_spec)) {
        return drush_set_error(dt('Could not drop and create database: @name', array('@name' => $db_name)));
      }
    }
  }
  return TRUE;
}

/**
 * Command callback.
 */
function drush_core_site_install($profile = NULL) {
  drush_include_engine('drupal', 'site_install', drush_drupal_major_version());
  drush_core_site_install_version($profile);
}

/**
 * Return a db_spec based on supplied db_url/db_prefix options or an existing settings.php.
 */
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
