<?php

use Drush\Log\LogLevel;
use Drupal\Core\Config\FileStorage;

function site_install_drush_command() {
  $items['site-install'] = array(
    'description' => 'Install Drupal along with modules/themes/configuration using the specified install profile.',
    'arguments' => array(
      // In Drupal 7 installation profiles can be marked as exclusive by placing
      // a
      // @code
      //   exclusive: true
      // @endcode
      // line in the profile's info file.
      // See https://www.drupal.org/node/1022020 for more information.
      //
      // In Drupal 8 you can turn your installation profile into a distribution
      // by providing a
      // @code
      //   distribution:
      //     name: 'Distribution name'
      // @endcode
      // block in the profile's info YAML file.
      // See https://www.drupal.org/node/2210443 for more information.
      'profile' => 'The install profile you wish to run. Defaults to \'default\' in D6, \'standard\' in D7+, unless an install profile is marked as exclusive (or as a distribution in D8+ terminology) in which case that is used.',
      'key=value...' => 'Any additional settings you wish to pass to the profile. Fully supported on D7+, partially supported on D6 (single step configure forms only). The key is in the form [form name].[parameter name] on D7 or just [parameter name] on D6.',
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
      'clean-url'=> 'Defaults to clean; use --no-clean-url to disable. Note that Drupal 8 and later requires clean.',
      'site-name' => 'Defaults to Site-Install',
      'site-mail' => 'From: for system mailings. Defaults to admin@example.com',
      'sites-subdir' => array(
        'description' => "Name of directory under 'sites' which should be created. Only needed when the subdirectory does not already exist. Defaults to 'default'",
        'value' => 'required',
        'example-value' => 'directory_name',
      ),
      'config-dir' => 'A path pointing to a full set of configuration which should be imported after installation.',
    ),
    'examples' => array(
      'drush site-install expert --locale=uk' => '(Re)install using the expert install profile. Set default language to Ukrainian.',
      'drush site-install --db-url=mysql://root:pass@localhost:port/dbname' => 'Install using the specified DB params.',
      'drush site-install --db-url=sqlite://sites/example.com/files/.ht.sqlite' => 'Install using SQLite (D7+ only).',
      'drush site-install --account-name=joe --account-pass=mom' => 'Re-install with specified uid1 credentials.',
      'drush site-install standard install_configure_form.site_default_country=FR my_profile_form.my_settings.key=value' => 'Pass additional arguments to the profile (D7 example shown here - for D6, omit the form id).',
      "drush site-install standard install_configure_form.update_status_module='array(FALSE,FALSE)'" => 'Disable email notification during install and later. If your server has no smtp, this gets rid of an error during install.',
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
      unset($command['options']['config-dir']);
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
      drush_log(dt('Only lowercase sites-subdir are valid. Switching to !lower.', array('!lower' => $lower)), LogLevel::WARNING);
      drush_set_option('sites-subdir', $lower);
    }
    // Make sure that we will bootstrap to the 'sites-subdir' site.
    drush_set_context('DRUSH_SELECTED_URI', 'http://' . $sites_subdir);
  }

  if ($config = drush_get_option('config-dir')) {
    if (!file_exists($config)) {
      return drush_set_error('config_import_target', 'The config source directory does not exist.');
    }
    if (!is_dir($config)) {
      return drush_set_error('config_import_target', 'The config source is not a directory.');
    }
  }
}

/**
 * Perform setup tasks for installation.
 */
function drush_core_pre_site_install($profile = NULL) {
  $sql = drush_sql_get_class();
  if (!$db_spec = $sql->db_spec()) {
    drush_set_error(dt('Could not determine database connection parameters. Pass --db-url option.'));
    return;
  }

  // Make sure URI is set so we get back a proper $alias_record. Needed for quick-drupal.
  _drush_bootstrap_selected_uri();

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
  $default = realpath($alias_record['root'] . '/sites/default');
  $sitesfile_write = drush_drupal_major_version() >= 8 && $conf_path != $default && !file_exists($sitesfile);

  if (!file_exists($settingsfile)) {
    $msg[] = dt('create a @settingsfile file', array('@settingsfile' => $settingsfile));
  }
  if ($sitesfile_write) {
    $msg[] = dt('create a @sitesfile file', array('@sitesfile' => $sitesfile));
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
  else {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  }

  if (!$sql->drop_or_create($db_spec)) {
    return drush_set_error(dt('Failed to create database: @error', array('@error' => implode(drush_shell_exec_output()))));
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

      // Allow for numeric and NULL values to be passed in.
      if (is_numeric($value)) {
        $value = intval($value);
      }
      elseif ($value == 'NULL') {
        $value = NULL;
      }

      $form_options[$key] = $value;
    }
  }

  // If the profile is not explicitly set, default to the 'minimal' for an issue-free config import.
  if (empty($profile) && drush_get_option('config-dir')) {
    $profile = 'minimal';
  }

  drush_include_engine('drupal', 'site_install');
  drush_core_site_install_version($profile, $form_options);

  // Post installation run the configuration import.
  if ($config = drush_get_option('config-dir')) {
    // Set the destination site UUID to match the source UUID, to bypass a core fail-safe.
    $source_storage = new FileStorage($config);
    drush_op('drush_config_set', 'system.site', 'uuid', $source_storage->read('system.site')['uuid']);
    // Run a full configuration import.
    drush_invoke_process('@self', 'config-import', array(), array('source' => $config));
  }
}
