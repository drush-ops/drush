<?php
// $Id$

// Perform setup tasks for installation.
function drush_core_pre_installcore() {
  
  $db_spec = drush_core_installcore_db_spec();
  
  $sites_subdir = drush_get_option('sites-subdir', 'default');
  $conf_path = "sites/$sites_subdir";
  $files = "$conf_path/files";
  $settingsfile = "$conf_path/settings.php";
  if (!file_exists($files)) {
    $msg[] = dt('create a $files directory');
  }
  if (!file_exists($settingsfile)) {
    $msg[] = dt('create a @settingsfile file', array('@settingsfile' => $settingsfile));
  }
  $msg[] = dt("DROP your '@db' database and then CREATE a new one.", array('@db' => $db_spec['database']));
  
  if (!drush_confirm(dt('You are about to ') . implode(dt(' and '), $msg) . ' Do you want to continue?')) {
    return drush_set_error('CORE_INSTALLCORE_ABORT', 'Aborting.');
  }
  
  // Can't install without sites directory and settings.php.
  if (!file_exists($conf_path)) {
    if (!drush_op('mkdir', $conf_path)) {
      drush_set_error(dt('Failed to create directory @conf_path', array('@conf_path' => $conf_path)));
      return;
    }
  }
  else {
    drush_log(dt('Sites directory @subdir already exists - proceeding.', array('@subdir' => $conf_path)));
  }
  if (!file_exists($settingsfile)) {
    if (!drush_op('copy', 'sites/default/default.settings.php', $settingsfile)) {
      drush_set_error(dt('Failed to copy sites/default/default.settings.php to  @settingsfile', array('@settingsfile' => $settingsfile)));
      return;
    }
  }
  
  
  // Add a files dir if needed
  if (!file_exists($files)) {
    if (!drush_op('mkdir', $files)) {
      drush_set_error(dt('Failed to create directory @name', array('@name' => $files)));
      return;
    }
  }
  
  // Now we can bootstrap up to the specified site.
  $uri = drush_get_option(array('l', 'uri'));
  if (empty($uri)) {
    // TODO: is there a better way?
    drush_set_option('uri', $conf_path);
  }
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  
  // Drop and create DB if needed.
  // Can't use drush_sql_query() since we have no DB.
  $exec = 'mysql -u' . _drush_sql_get_credentials($db_spec);
  // Strip out DB name from credentials. Soon it won't exist anymore. We do
  // need a DB name to connect to so use built-in mysql DB.
  $replacement_db = 'information_schema';
  $exec = str_replace($db_spec['database'], $replacement_db, $exec) . ' -e ';
  if (drush_op('system', $exec . '"DROP DATABASE IF EXISTS ' . $db_spec['database'] . '"')) {
    drush_set_error(dt('Could not drop database: @name', array('@name' => $db_spec['database'])));
    return;
  }
  ;
  if (drush_op('system', $exec . '"CREATE DATABASE ' . $db_spec['database'] . '"')) {
    drush_set_error(dt('Could not create new database: @name', array('@name' => $db_spec['database'])));
    return;
  }
}

function drush_core_installcore($profile = 'default') {
  require_once('install.php');
  $db_spec = drush_core_installcore_db_spec();
  
  $account_pass = drush_get_option('account_pass', 'admin');
  $settings = array(
    'parameters' => array(
      'profile' => $profile,
      'locale' => 'en',
    ),
    'forms' => array(
      'install_settings_form' => $db_spec,
      'install_configure_form' => array(
        'site_name' => drush_get_option('site_name', 'Installcore'),
        'site_mail' => drush_get_option('site_mail', 'admin@example.com'),
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
        'clean_url' => drush_get_option('clean_url', TRUE),
      ),
    ),
  );
  drush_log(dt('Starting core Drupal installation. This takes a few seconds or more depending on your install profile ...'), 'ok');
  install_drupal($settings);
}

// Return a db_spec based on supplied options.
function drush_core_installcore_db_spec() {
  return array(
    'driver' => drush_get_option('db-driver', 'mysql'),
    'host' => drush_get_option('db-host', 'localhost'),
    'port' => drush_get_option('db-port', '3306'),
    'database' => drush_get_option('db-name', 'installcore'),
    'username' => drush_get_option('db-user', 'root'),
    'password' => drush_get_option('db-pass', ''),
  );
}