<?php
// $Id$

// Perform setup tasks for installation.
function drush_core_pre_site_install() {

  if (!$db_spec = drush_core_site_install_db_spec()) {
    drush_set_error(dt('Could not determine database connection parameters. Pass --db-url option.'));
    return;
  }

  
  if ($sites_subdir = drush_get_option('sites-subdir')) {
    // Needed iso that we later bootstrap into the right site.
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
  // TODO: support db-su like sql sync.
  // Can't use drush_sql_query() since might not have a DB.
  $exec = 'mysql ' . _drush_sql_get_credentials($db_spec);
  // Strip DB name from credentials. Soon it won't exist anymore. We do
  // need a DB name to connect to so use built-in mysql DB.
  $replacement_db = 'information_schema';
  // Make sure we are only replacing the database name,
  // and not a username or password that is the same as the database name.
  $exec = str_replace(" {$db_spec['database']}", " {$replacement_db}", $exec) . ' -e ';
  // Quote database name with backticks, and add escaping for the shell.
  $database_name = "\`{$db_spec['database']}\`";
  if (drush_op('system', $exec . ' "DROP DATABASE IF EXISTS ' . $database_name . '"') && !drush_get_context('DRUSH_SIMULATE')) {
    drush_set_error(dt('Could not drop database: @name', array('@name' => $db_spec['database'])));
    return;
  }

  if (drush_op('system', $exec . '"CREATE DATABASE ' . $database_name . '"') && !drush_get_context('DRUSH_SIMULATE')) {
    drush_set_error(dt('Could not create new database: @name', array('@name' => $db_spec['database'])));
    return;
  }
}

function drush_core_site_install($profile = 'standard') {
  define('MAINTENANCE_MODE', 'install');
  require_once DRUPAL_ROOT . '/includes/install.core.inc';

  $db_spec = drush_core_site_install_db_spec();

  $account_pass = drush_get_option('account-pass', 'admin');
  $settings = array(
    'parameters' => array(
      'profile' => $profile,
      'locale' => drush_get_option('locale', 'en'),
    ),
    'forms' => array(
      'install_settings_form' => $db_spec,
      'install_configure_form' => array(
        'site_name' => drush_get_option('site-name', 'Site-Install'),
        'site_mail' => drush_get_option('site-mail', 'admin@example.com'),
        'account' => array(
          'name' => drush_get_option('account-name', 'admin'),
          'mail' => drush_get_option('account-mail', 'admin@example.com'),
          'pass' => array(
            'pass1' => $account_pass,
            'pass2' => $account_pass,
          ),
        ),
        'update_status_module' => array(
          1 => TRUE,
          2 => TRUE,
        ),
        'clean_url' => drush_get_option('clean-url', TRUE),
      ),
    ),
  );
  drush_log(dt('Starting Drupal installation. This takes 30 seconds or so ...'), 'ok');
  install_drupal($settings);
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
