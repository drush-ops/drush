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
    // Make sure that we will bootstrap to the 'sites-subdir' site.
    drush_set_option('uri', 'http://' . $sites_subdir);
  }
}

/**
 * Perform setup tasks for installation.
 */
function drush_core_pre_site_install($profile = NULL) {
  if (!$db_spec = _drush_sql_get_db_spec()) {
    drush_set_error(dt('Could not determine database connection parameters. Pass --db-url option.'));
    return;
  }
  $default_sites_subdir = drush_get_context('DRUSH_DRUPAL_SITE', 'default');
  $sites_subdir = drush_get_option('sites-subdir', $default_sites_subdir);

  $conf_path = "sites/$sites_subdir";
  $files = "$conf_path/files";
  $settingsfile = "$conf_path/settings.php";
  $sitesfile = "sites/sites.php";
  $sitesfile_write = drush_drupal_major_version() >= 8 && !file_exists($sitesfile) && $sites_subdir != 'default';
  if (!file_exists($files)) {
    $msg[] = dt('create a @files directory', array('@files' => $files));
  }
  if (!file_exists($settingsfile)) {
    $msg[] = dt('create a @settingsfile file', array('@settingsfile' => $settingsfile));
  }
  if ($sitesfile_write) {
    $msg[] = dt('create a @sitesfile file', array('@sitesfile' => $sitesfile));
  }
  if (drush_sql_db_exists($db_spec)) {
    $msg[] = dt("DROP all tables in your '@db' database.", array('@db' => $db_spec['database']));
  }
  else {
    $msg[] = dt("CREATE  the '@db' database.", array('@db' => $db_spec['database']));
  }

  if (!drush_confirm(dt('You are about to ') . implode(dt(' and '), $msg) . ' Do you want to continue?')) {
    return drush_user_abort();
  }

  // Can't install without sites subdirectory and settings.php.
  if (!file_exists($conf_path)) {
    if (!drush_mkdir($conf_path) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to create directory @conf_path', array('@conf_path' => $conf_path)));
      return;
    }
  }
  else {
    drush_log(dt('Sites directory @subdir already exists - proceeding.', array('@subdir' => $conf_path)));
  }

  if (!drush_file_not_empty($settingsfile)) {
    if (!drush_op('copy', 'sites/default/default.settings.php', $settingsfile) && !drush_get_context('DRUSH_SIMULATE')) {
      return drush_set_error(dt('Failed to copy sites/default/default.settings.php to  @settingsfile', array('@settingsfile' => $settingsfile)));
    }

    if (drush_drupal_major_version() == 6) {
      // On D6, we have to write $db_url ourselves. In D7+, the installer does it.
      file_put_contents($settingsfile, "\n" . '$db_url = \'' . drush_get_option('db-url') . "';\n", FILE_APPEND);
      // Instead of parsing and performing string replacement on the configuration file,
      // the options are appended and override the defaults.
      // Database table prefix
      if (!empty($db_spec['db_prefix'])) {
        if (is_array($db_spec['db_prefix'])) {
          // Write db_prefix configuration as an array
          $db_prefix_config = '$db_prefix = ' . var_export($db_spec['db_prefix'], TRUE) . ';';
        }
        else {
          // Write db_prefix configuration as a string
          $db_prefix_config = '$db_prefix = \'' . $db_spec['db_prefix'] . '\';';
        }
        file_put_contents($settingsfile, "\n" . $db_prefix_config . "\n", FILE_APPEND);
      }
    }
  }

  // Write an empty sites.php if we are on D8 and using multi-site.
  if ($sitesfile_write) {
    if (!drush_op('copy', 'sites/example.sites.php', $sitesfile) && !drush_get_context('DRUSH_SIMULATE')) {
      return drush_set_error(dt('Failed to copy sites/sites.php to @sitesfile', array('@sitesfile' => $sitesfile)));
    }
  }

  // The Drupal 6 installer needs to bootstrap up to the specified site.
  // We need to be at least at DRUSH_BOOTSTRAP_DRUPAL_SITE to select the site uri to install to
  define('MAINTENANCE_MODE', 'install');
  if (drush_drupal_major_version() == 6) {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  }
  elseif ($sites_subdir != 'default') {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  }

  // Add a files dir if needed
  if (!file_exists($files)) {
    if (!drush_mkdir($files) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to create directory @name', array('@name' => $files)));
      return;
    }
  }

  // Empty or create the DB as needed.
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
    drush_sql_empty_db($db_spec);
  }

  if (drush_drupal_major_version() >= 8) {
    // Empty out any existing config directories.
    $directories = array();
    // Check for alternative format:
    // @code
    // $config_directories = array(
    //   'active' => array(
    //     'path' => 'config/active',
    //   ),
    //   'staging' => array(
    //     'path' => 'config/staging',
    //   ),
    // );
    // @endcode
    preg_match('/\n\$config_directories[^;]*\'path\' => \'([^\']*)\'.*\'path\' => \'([^\']*)\'/s', file_get_contents($settingsfile), $directories);

    if (empty($directories)) {
      // Check for alternative format:
      // @code
      // $config_directories['active']['path'] = 'config/active';
      // $config_directories['staging']['path'] = 'config/staging';
      // @endcode
      foreach (array('active', 'staging') as $type) {
        preg_match('/\n\$config_directories[^;]*\'' . $type . '\'\]\[\'path\'\] = \'([^\']*)\'/', file_get_contents($settingsfile), $directory);
        if (isset($directory[1])) {
          $directories[$type] = $files . '/' . $directory[1];
        }
      }
    }
    else {
      $directories['active'] =  $files . '/' . $directories[1];
      $directories['staging'] =  $files . '/' . $directories[2];
    }

    foreach ($directories as $directory) {
      if (file_exists($directory)) {
        drush_delete_dir_contents($directory, TRUE);
      }
    }

    // Remove files/php if needed.
    drush_delete_dir("$files/php", TRUE);
  }

  return TRUE;
}

/**
 * Command callback.
 */
function drush_core_site_install($profile = NULL) {
  $args = func_get_args();
  $form_options = array();

  if ($args) {
    // The first argument is the profile.
    $profile = array_shift($args);
    // Subsequent arguments are additional form values.
    foreach ($args as $arg) {
      list($key, $value) = explode('=', $arg);
      $form_options[$key] = $value;
    }
  }
  drush_include_engine('drupal', 'site_install', drush_drupal_major_version());
  drush_core_site_install_version($profile, $form_options);
}
