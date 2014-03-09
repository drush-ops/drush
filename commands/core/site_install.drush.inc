<?php

function site_install_drush_command() {
  $items['site-install'] = array(
    'description' => 'Install Drupal along with modules/themes/configuration using the specified install profile.',
    'arguments' => array(
      'profile' => 'the install profile you wish to run. defaults to \'default\' in D6, \'standard\' in D7+',
      'key=value...' => 'any additional settings you wish to pass to the profile. Fully supported on D7+, partially supported on D6 (single step configure forms only). The key is in the form [form name].[parameter name] on D7 or just [parameter name] on D6.',
    ),
    'options' => array(
      'db-url' => array(
        'description' => 'A Drupal 6 style database URL. Only required for initial install - not re-install.',
        'example-value' => 'mysql://root:pass@host/db',
      ),
      'db-prefix' => 'An optional table prefix to use for initial install.  Can be a key-value array of tables/prefixes in a drushrc file (not the command line).',
      'db-su' => array(
        'description' => 'Account to use when creating a new database. Must have Grant permission (mysql only). Optional.',
        'example-value' => 'root',
      ),
      'db-su-pw' => array(
        'description' => 'Password for the "db-su" account. Optional.',
        'example-value' => 'pass',
      ),
      'account-name' => 'uid1 name. Defaults to admin',
      'account-pass' => 'uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in drushrc.php.',
      'account-mail' => 'uid1 email. Defaults to admin@example.com',
      'locale' => array(
        'description' => 'A short language code. Sets the default site language. Language files must already be present. You may use download command to get them.',
        'example-value' => 'en-GB',
      ),
      'clean-url'=> 'Defaults to 1',
      'site-name' => 'Defaults to Site-Install',
      'site-mail' => 'From: for system mailings. Defaults to admin@example.com',
      'sites-subdir' => array(
        'description' => "Name of directory under 'sites' which should be created. Only needed when the subdirectory does not already exist. Defaults to 'default'",
        'value' => 'required',
        'example-value' => 'directory_name',
      ),
      'writable' => 'Make CMI and other dirs writable by both web and CLI users. Suitable for non Prod environments.',
    ),
    'examples' => array(
      'drush site-install expert --locale=uk' => '(Re)install using the expert install profile. Set default language to Ukrainian.',
      'drush site-install --db-url=mysql://root:pass@localhost:port/dbname' => 'Install using the specified DB params.',
      'drush site-install --db-url=sqlite://sites/example.com/files/.ht.sqlite' => 'Install using SQLite (D7+ only).',
      'drush site-install --account-name=joe --account-pass=mom' => 'Re-install with specified uid1 credentials.',
      'drush site-install standard install_configure_form.site_default_country=FR my_profile_form.my_settings.key=value' => 'Pass additional arguments to the profile (D7 example shown here - for D6, omit the form id).',
      "drush site-install install_configure_form.update_status_module='array(FALSE,FALSE)'" => 'Disable email notification during install and later. If your server has no smtp, this gets rid of an error during install.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
    'aliases' => array('si'),
  );
  return $items;
}

/**
 * Implements hook_drush_help_alter().
 */
function site_install_drush_help_alter(&$command) {
  // Drupal version-specific customizations.
  if ($command['command'] == 'site-install') {
    if (drush_drupal_major_version() >= 8) {
      unset($command['options']['clean-url']);
    }
    else {
      unset($command['options']['writable']);
    }
  }
}

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
  $sql = drush_sql_get_class();
  if (!$db_spec = $sql->db_spec) {
    drush_set_error(dt('Could not determine database connection parameters. Pass --db-url option.'));
    return;
  }

  $alias_record = drush_sitealias_get_record('@self');
  $sites_subdir = drush_sitealias_local_site_path($alias_record);
  // Override with sites-subdir if specified.
  if ($dir = drush_get_option('sites-subdir')) {
    $sites_subdir = "sites/$dir";
  }
  $conf_path = $sites_subdir;
  // Handle the case where someuse uses --variables to set the file public path. Won't work on D8+.
  $files = !empty($GLOBALS['conf']['files_public_path']) ? $GLOBALS['conf']['files_public_path'] : "$conf_path/files";
  $settingsfile = "$conf_path/settings.php";
  $sitesfile = "sites/sites.php";
  $default = $alias_record['root'] . '/sites/default';
  $sitesfile_write = drush_drupal_major_version() >= 8 && $conf_path != $default && !file_exists($sitesfile);
  
  if (!file_exists($settingsfile)) {
    $msg[] = dt('create a @settingsfile file', array('@settingsfile' => $settingsfile));
  }
  if ($sitesfile_write) {
    $msg[] = dt('create a @sitesfile file', array('@sitesfile' => $sitesfile));
  }
  if (drush_drupal_major_version() >= 8) {
    $msg[] = dt('empty any Config directories');
  }
  if ($sql->db_exists()) {
    $msg[] = dt("DROP all tables in your '@db' database.", array('@db' => $db_spec['database']));
  }
  else {
    $msg[] = dt("CREATE the '@db' database.", array('@db' => $db_spec['database']));
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
      return drush_set_error(dt('Failed to copy sites/default/default.settings.php to @settingsfile', array('@settingsfile' => $settingsfile)));
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

  // We need to be at least at DRUSH_BOOTSTRAP_DRUPAL_SITE to select the site uri to install to
  define('MAINTENANCE_MODE', 'install');
  if (drush_drupal_major_version() == 6) {
    // The Drupal 6 installer needs to bootstrap up to the specified site.
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  }
  elseif ($conf_path != $default) {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  }

  $sql->drop_or_create($db_spec);

  if (drush_drupal_major_version() >= 8) {
    // Empty out any existing config directories.
    $directories = array();
    // Check for alternative format:
    // @code
    // $config_directories = array(
    //   'active' => 'config/active',
    //   'staging' => 'config/staging',
    // );
    // @endcode
    // @todo update this regex to omit path.
    // preg_match('/\n\$config_directories[^;]*\'path\' => \'([^\']*)\'.*\'path\' => \'([^\']*)\'/s', file_get_contents($settingsfile), $directories);

    if (empty($directories)) {
      // Check for canonical format:
      // @code
      // $config_directories['active'] = 'config/active';
      // $config_directories['staging'] = 'config/staging';
      // @endcode
      foreach (array('active', 'staging') as $type) {
        preg_match('/\n\$config_directories[^;]*\'' . $type . '\'\] = \'([^\']*)\'/', file_get_contents($settingsfile), $directory);
        if (isset($directory[1])) {
          $directories[$type] = $directory[1];
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

    if (drush_get_option('writable')) {
      if (is_writable($settingsfile)) {
        $chmod = <<<'EOD'
// Appended by Drush site-install.
$settings['file_chmod_directory'] = 0777;
$settings['file_chmod_file'] = 0777;
EOD;
        drush_file_append_data($settingsfile, "\n$chmod\n");
      }
      else {
        drush_log(dt('Not appending file_chmod_* to @settingsfile as it is not writable.', array('@settingsfile' => $settingsfile)), 'notice');
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
